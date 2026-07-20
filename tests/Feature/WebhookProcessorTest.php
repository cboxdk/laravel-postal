<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Events\PostalDomainDnsError;
use Cbox\LaravelPostal\Events\PostalMessageBounced;
use Cbox\LaravelPostal\Events\PostalMessageLinkClicked;
use Cbox\LaravelPostal\Events\PostalMessageLoaded;
use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Cbox\LaravelPostal\Webhooks\WebhookEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function envelope(array $body): WebhookEnvelope
{
    $envelope = WebhookEnvelope::fromArray($body);

    if ($envelope === null) {
        throw new RuntimeException('Fixture body did not parse into an envelope.');
    }

    return $envelope;
}

function statusBody(string $event = 'MessageSent', array $message = [], array $payload = [], ?string $uuid = 'uuid-1'): array
{
    return [
        'event' => $event,
        'timestamp' => 1752969600.5,
        'uuid' => $uuid,
        'payload' => array_replace([
            'message' => array_replace([
                'id' => 4200,
                'token' => 'tok',
                'direction' => 'outgoing',
                'message_id' => 'mid@postal',
                'to' => 'alice@example.com',
                'from' => 'no-reply@cboxid.com',
                'subject' => 'Welcome',
                'timestamp' => 1752969599.0,
                'spam_status' => 'NotChecked',
                'tag' => 'onboarding',
            ], $message),
            'status' => 'Sent',
            'details' => 'accepted',
            'output' => '250 OK',
            'sent_with_ssl' => true,
            'timestamp' => 1752969600.1,
            'time' => 0.5,
        ], $payload),
    ];
}

it('stores the event, upserts the message row and dispatches the typed event', function (): void {
    Event::fake([PostalMessageSent::class]);

    $result = app(WebhookProcessor::class)->process('default', envelope(statusBody()));

    expect($result)->toBeInstanceOf(PostalMessageSent::class);

    Event::assertDispatched(PostalMessageSent::class, function (PostalMessageSent $event): bool {
        return $event->server() === 'default'
            && $event->payload->message->id === 4200
            && $event->payload->status === 'Sent'
            && $event->uuid() === 'uuid-1';
    });

    $message = PostalMessage::query()->sole();

    expect($message->server)->toBe('default')
        ->and($message->postal_message_id)->toBe(4200)
        ->and($message->to)->toBe('alice@example.com')
        ->and($message->subject)->toBe('Welcome')
        ->and($message->tag)->toBe('onboarding')
        ->and($message->status)->toBe('Sent')
        ->and($message->status_details)->toBe('accepted')
        ->and($message->last_event)->toBe('MessageSent');

    expect(PostalMessageEvent::query()->sole()->dedupe_key)->toBe('default:uuid-1');
});

it('drops a redelivered webhook with the same uuid before any side effect', function (): void {
    Event::fake([PostalMessageSent::class]);

    $processor = app(WebhookProcessor::class);

    expect($processor->process('default', envelope(statusBody())))->not->toBeNull()
        ->and($processor->process('default', envelope(statusBody())))->toBeNull();

    Event::assertDispatchedTimes(PostalMessageSent::class, 1);
    expect(PostalMessageEvent::query()->count())->toBe(1);
});

it('processes the same uuid for two different servers independently', function (): void {
    Event::fake([PostalMessageSent::class]);

    $processor = app(WebhookProcessor::class);

    expect($processor->process('default', envelope(statusBody())))->not->toBeNull()
        ->and($processor->process('second', envelope(statusBody())))->not->toBeNull();

    expect(PostalMessage::query()->count())->toBe(2);
});

it('tracks opens and clicks as counters on the message row', function (): void {
    Event::fake([PostalMessageLoaded::class, PostalMessageLinkClicked::class]);

    $processor = app(WebhookProcessor::class);

    $processor->process('default', envelope([
        'event' => 'MessageLoaded',
        'timestamp' => 1752969601.0,
        'uuid' => 'load-1',
        'payload' => [
            'message' => ['id' => 4200, 'to' => 'alice@example.com'],
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0',
        ],
    ]));

    $processor->process('default', envelope([
        'event' => 'MessageLinkClicked',
        'timestamp' => 1752969602.0,
        'uuid' => 'click-1',
        'payload' => [
            'message' => ['id' => 4200, 'to' => 'alice@example.com'],
            'url' => 'https://cboxid.com/welcome',
            'token' => 'linktok',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0',
        ],
    ]));

    $message = PostalMessage::query()->sole();

    expect($message->opens)->toBe(1)
        ->and($message->clicks)->toBe(1)
        ->and($message->last_event)->toBe('MessageLinkClicked');

    Event::assertDispatched(PostalMessageLoaded::class);
    Event::assertDispatched(PostalMessageLinkClicked::class, function (PostalMessageLinkClicked $event): bool {
        return $event->payload->url === 'https://cboxid.com/welcome';
    });
});

it('marks the original message bounced on MessageBounced', function (): void {
    Event::fake([PostalMessageBounced::class]);

    app(WebhookProcessor::class)->process('default', envelope([
        'event' => 'MessageBounced',
        'timestamp' => 1752969603.0,
        'uuid' => 'bounce-1',
        'payload' => [
            'original_message' => [
                'id' => 4200,
                'to' => 'alice@example.com',
                'subject' => 'Welcome',
            ],
            'bounce' => [
                'id' => 9999,
                'from' => 'mailer-daemon@example.com',
            ],
        ],
    ]));

    $message = PostalMessage::query()->sole();

    expect($message->postal_message_id)->toBe(4200)
        ->and($message->status)->toBe('Bounced');

    Event::assertDispatched(PostalMessageBounced::class, function (PostalMessageBounced $event): bool {
        return $event->payload->bounce->id === 9999
            && $event->payload->originalMessage->id === 4200;
    });
});

it('dispatches DomainDNSError with a null message row', function (): void {
    Event::fake([PostalDomainDnsError::class]);

    app(WebhookProcessor::class)->process('default', envelope([
        'event' => 'DomainDNSError',
        'timestamp' => 1752969604.0,
        'uuid' => 'dns-1',
        'payload' => [
            'server' => ['uuid' => 'srv-uuid', 'name' => 'cbox-id', 'permalink' => 'cbox-id', 'organization' => 'cbox'],
            'domain' => 'cboxid.com',
            'uuid' => 'domain-uuid',
            'dns_checked_at' => 1752969600.0,
            'spf_status' => 'OK',
            'spf_error' => null,
            'dkim_status' => 'Invalid',
            'dkim_error' => 'DKIM record not found',
            'mx_status' => 'OK',
            'mx_error' => null,
            'return_path_status' => 'OK',
            'return_path_error' => null,
        ],
    ]));

    expect(PostalMessage::query()->count())->toBe(0)
        ->and(PostalMessageEvent::query()->sole()->postal_message_id)->toBeNull();

    Event::assertDispatched(PostalDomainDnsError::class, function (PostalDomainDnsError $event): bool {
        return $event->payload->domain === 'cboxid.com'
            && $event->payload->dkimStatus === 'Invalid'
            && $event->payload->server->name === 'cbox-id';
    });
});

it('ignores unknown event types without storing anything', function (): void {
    Event::fake();

    $result = app(WebhookProcessor::class)->process('default', envelope([
        'event' => 'SomethingNew',
        'timestamp' => 1752969605.0,
        'uuid' => 'new-1',
        'payload' => [],
    ]));

    expect($result)->toBeNull()
        ->and(PostalMessageEvent::query()->count())->toBe(0);
});

it('still dispatches events when the store is disabled', function (): void {
    config()->set('postal.webhooks.store', false);

    Event::fake([PostalMessageSent::class]);

    app(WebhookProcessor::class)->process('default', envelope(statusBody()));

    Event::assertDispatched(PostalMessageSent::class);
    expect(PostalMessage::query()->count())->toBe(0)
        ->and(PostalMessageEvent::query()->count())->toBe(0);
});

it('deduplicates by content hash when the uuid is absent', function (): void {
    Event::fake([PostalMessageSent::class]);

    $processor = app(WebhookProcessor::class);

    expect($processor->process('default', envelope(statusBody(uuid: null))))->not->toBeNull()
        ->and($processor->process('default', envelope(statusBody(uuid: null))))->toBeNull();

    Event::assertDispatchedTimes(PostalMessageSent::class, 1);
});

it('processes a full signed delivery end to end through the HTTP route', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());

    Event::fake([PostalMessageSent::class]);

    $body = WebhookFixtures::messageSentBody();

    $this->call('POST', '/postal/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_POSTAL_SIGNATURE_256' => WebhookFixtures::sign256($body),
    ], $body)->assertOk();

    // The default queue connection in Testbench is sync, so the job has
    // already run: the store is populated and the event dispatched.
    Event::assertDispatched(PostalMessageSent::class);

    $message = PostalMessage::query()->sole();

    expect($message->postal_message_id)->toBe(4200)
        ->and($message->status)->toBe('Sent');
});
