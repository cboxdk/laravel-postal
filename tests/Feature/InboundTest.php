<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\InboundProcessor;
use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Inbound\InboundMessage;
use Cbox\LaravelPostal\Inbound\ProcessInboundMessage;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\call;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
});

function inboundHashBody(array $overrides = []): string
{
    $body = array_replace([
        'id' => 7100,
        'rcpt_to' => 'support@cboxid.com',
        'mail_from' => 'alice@example.com',
        'token' => 'inbound-tok',
        'subject' => 'Help me please',
        'message_id' => 'inbound-mid@example.com',
        'timestamp' => 1752969700.5,
        'size' => 2048,
        'spam_status' => 'NotSpam',
        'bounce' => false,
        'received_with_ssl' => true,
        'to' => 'Support <support@cboxid.com>',
        'cc' => null,
        'from' => 'Alice <alice@example.com>',
        'date' => 'Sun, 20 Jul 2026 10:00:00 +0200',
        'in_reply_to' => null,
        'references' => null,
        'html_body' => '<p>Help!</p>',
        'plain_body' => 'Help!',
        'attachment_quantity' => 1,
        'auto_submitted' => null,
        'reply_to' => ['alice@example.com'],
        'attachments' => [[
            'filename' => 'screenshot.png',
            'content_type' => 'image/png',
            'size' => 3,
            'data' => base64_encode('png'),
        ]],
    ], $overrides);

    return (string) json_encode($body);
}

function postInbound(string $uri, string $rawBody, array $headers = [], string $contentType = 'application/json'): TestResponse
{
    $server = ['CONTENT_TYPE' => $contentType];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return call('POST', $uri, [], [], [], $server, $rawBody);
}

it('accepts a signed inbound delivery and queues processing', function (): void {
    Queue::fake();

    $body = inboundHashBody();

    postInbound('/postal/inbound', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    Queue::assertPushed(ProcessInboundMessage::class, function (ProcessInboundMessage $job): bool {
        return $job->server === 'default' && $job->body['id'] === 7100;
    });
});

it('rejects an unsigned inbound delivery with 401', function (): void {
    Queue::fake();

    postInbound('/postal/inbound', inboundHashBody())->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects inbound deliveries for unknown servers', function (): void {
    $body = inboundHashBody();

    postInbound('/postal/inbound/nope', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertNotFound();
});

it('attributes inbound mail to the server in the URL and processes end to end', function (): void {
    Event::fake([PostalInboundMessage::class]);

    $body = inboundHashBody();

    postInbound('/postal/inbound/second', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    Event::assertDispatched(PostalInboundMessage::class, function (PostalInboundMessage $event): bool {
        return $event->server() === 'second'
            && $event->payload->subject === 'Help me please'
            && $event->payload->attachments[0]->content() === 'png'
            && $event->payload->attachments[0]->filename === 'screenshot.png';
    });

    $row = PostalMessage::query()->sole();

    expect($row->server)->toBe('second')
        ->and($row->postal_message_id)->toBe(7100)
        ->and($row->direction)->toBe('incoming')
        ->and($row->status)->toBe('Received')
        ->and($row->to)->toBe('support@cboxid.com')
        ->and($row->from)->toBe('alice@example.com')
        ->and($row->last_event)->toBe('InboundMessage');

    // Attachment data stays out of the event log.
    $event = PostalMessageEvent::query()->sole();

    expect($event->payload)->not->toHaveKey('attachments')
        ->and($event->dedupe_key)->toBe('second:inbound:7100');
});

it('drops redelivered inbound messages before side effects', function (): void {
    Event::fake([PostalInboundMessage::class]);

    $processor = app(InboundProcessor::class);
    $message = InboundMessage::fromArray((array) json_decode(inboundHashBody(), true));

    expect($processor->process('default', $message))->not->toBeNull()
        ->and($processor->process('default', $message))->toBeNull();

    Event::assertDispatchedTimes(PostalInboundMessage::class, 1);
    expect(PostalMessageEvent::query()->count())->toBe(1);
});

it('parses the RawMessage endpoint format', function (): void {
    $raw = "From: alice@example.com\r\nSubject: Raw inbound\r\n\r\nBody here";

    $message = InboundMessage::fromArray([
        'id' => 7200,
        'rcpt_to' => 'support@cboxid.com',
        'mail_from' => 'alice@example.com',
        'message' => base64_encode($raw),
        'base64' => true,
        'size' => strlen($raw),
    ]);

    expect($message->isRaw())->toBeTrue()
        ->and($message->rawMessage())->toBe($raw)
        ->and($message->id)->toBe(7200);
});

it('accepts the FormData encoding', function (): void {
    Queue::fake();

    $fields = [
        'id' => '7300',
        'rcpt_to' => 'support@cboxid.com',
        'mail_from' => 'alice@example.com',
        'subject' => 'Form encoded',
        'bounce' => 'false',
        'received_with_ssl' => 'true',
        'timestamp' => '1752969700.5',
    ];

    $rawBody = http_build_query($fields);

    // In a real request PHP parses the form body into POST itself; the test
    // kernel does not, so the fields are passed as parameters while the raw
    // body (what Postal signs) still carries the urlencoded payload.
    call('POST', '/postal/inbound', $fields, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_POSTAL_SIGNATURE_256' => WebhookFixtures::sign256($rawBody),
    ], $rawBody)->assertOk();

    Queue::assertPushed(ProcessInboundMessage::class, function (ProcessInboundMessage $job): bool {
        $message = InboundMessage::fromArray($job->body);

        return $message->id === 7300
            && $message->receivedWithSsl === true
            && $message->bounce === false
            && $message->subject === 'Form encoded';
    });
});

it('still dispatches inbound events when the store is disabled', function (): void {
    config()->set('postal.inbound.store', false);

    Event::fake([PostalInboundMessage::class]);

    app(InboundProcessor::class)->process('default', InboundMessage::fromArray((array) json_decode(inboundHashBody(), true)));

    Event::assertDispatched(PostalInboundMessage::class);
    expect(PostalMessage::query()->count())->toBe(0);
});

it('shows inbound messages in postal:tail', function (): void {
    $body = inboundHashBody();

    postInbound('/postal/inbound', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    $this->artisan('postal:tail', ['--once' => true])
        ->expectsOutputToContain('InboundMessage')
        ->assertExitCode(0);
});
