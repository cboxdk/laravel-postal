<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Exceptions\UnsupportedOperationException;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\Inbound\InboundMessage;
use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageStatusPayload;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('reports configured servers from the fake so webhook routes keep working', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());

    $this->fakePostal();

    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    $this->call('POST', '/postal/webhook/second', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_POSTAL_SIGNATURE_256' => WebhookFixtures::sign256($body),
    ], $body)->assertOk();
});

it('keeps the fake in the facade even when the real manager was resolved first', function (): void {
    expect(Postal::names())->toBe(['default', 'second']);

    $fake = Postal::fake();

    Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));

    $fake->assertSentCount(1);
});

it('passes the bounce flag through smtp-api sends', function (): void {
    config()->set('postal.servers.rawish', [
        'url' => 'https://postal.test',
        'key' => 'test-api-key',
        'type' => 'smtp-api',
    ]);

    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['message_id' => 'b@postal', 'messages' => []],
        ]),
    ]);

    Postal::server('rawish')->send(
        SendMessage::create()->to('a@b.c')->from('x@y.z')->plain('delivery report')->bounce(),
    );

    Http::assertSent(fn (Request $request): bool => $request['bounce'] === true);
});

it('refuses bounce sends over plain SMTP instead of dropping the flag', function (): void {
    config()->set('postal.servers.smtp-only', [
        'type' => 'smtp',
        'smtp' => ['host' => '127.0.0.1', 'port' => 1],
    ]);

    Postal::server('smtp-only')->send(
        SendMessage::create()->to('a@b.c')->from('x@y.z')->bounce(),
    );
})->throws(UnsupportedOperationException::class, 'bounce');

it('strips quoted display names correctly for envelope addresses', function (): void {
    $message = SendMessage::create()
        ->to('"Support <urgent>" <help@example.com>')
        ->from('Cbox <no-reply@cboxid.com>');

    expect($message->envelopeRecipients())->toBe(['help@example.com'])
        ->and($message->envelopeFrom())->toBe('no-reply@cboxid.com');
});

it('gives inbound events a usable idempotency handle', function (): void {
    $message = InboundMessage::fromArray(['id' => 7100, 'rcpt_to' => 'a@b.c']);

    expect($message->dedupeKey('default'))->toBe('default:inbound:7100');

    $withoutId = InboundMessage::fromArray(['rcpt_to' => 'a@b.c', 'subject' => 'x']);
    $otherWithoutId = InboundMessage::fromArray(['rcpt_to' => 'a@b.c', 'subject' => 'y']);

    // Distinct id-less payloads must not collapse onto one key.
    expect($withoutId->dedupeKey('default'))->not->toBe($otherWithoutId->dedupeKey('default'))
        ->and($withoutId->dedupeKey('default'))->toStartWith('default:inbound:');
});

it('keeps broadcast payloads free of bodies, attachments and raw MIME', function (): void {
    $event = new PostalInboundMessage(
        'default',
        InboundMessage::fromArray([
            'id' => 7100,
            'rcpt_to' => 'support@cboxid.com',
            'mail_from' => 'alice@example.com',
            'subject' => 'Hi',
            'plain_body' => 'SECRET BODY',
            'attachments' => [['filename' => 'a.bin', 'content_type' => 'application/octet-stream', 'size' => 4, 'data' => base64_encode(random_bytes(4))]],
            'message' => base64_encode(random_bytes(64)),
        ]),
        'default:inbound:7100',
        1752969700.0,
    );

    $payload = $event->broadcastWith();
    $json = json_encode($payload);

    expect($json)->toBeString()
        ->and($json)->not->toContain('SECRET BODY')
        ->and($payload['message'])->toBe([
            'id' => 7100,
            'token' => null,
            'message_id' => null,
            'rcpt_to' => 'support@cboxid.com',
            'mail_from' => 'alice@example.com',
            'subject' => 'Hi',
        ]);

    $sent = new PostalMessageSent(
        'default',
        MessageStatusPayload::fromArray([
            'message' => ['id' => 1, 'to' => 'a@b.c', 'subject' => 'S'],
            'status' => 'Sent',
            'output' => 'FULL SMTP TRANSCRIPT',
        ]),
        'uuid-1',
        1752969700.0,
    );

    expect(json_encode($sent->broadcastWith()))->not->toContain('FULL SMTP TRANSCRIPT');
});

it('rotates credentials through the mail transport after forget', function (): void {
    config()->set('mail.default', 'postal');
    config()->set('mail.mailers.postal', ['transport' => 'postal']);
    config()->set('mail.from', ['address' => 'no-reply@cboxid.com', 'name' => 'Cbox']);

    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['message_id' => 'ok@postal', 'messages' => []],
        ]),
    ]);

    Mail::raw('one', function ($message): void {
        $message->to('a@b.c')->subject('first');
    });

    // Rotate the key and drop the cached connection — the cached MAILER
    // stays, but the transport resolves a fresh connection per send.
    config()->set('postal.servers.default.key', 'rotated-key');
    Postal::forget('default');

    Mail::raw('two', function ($message): void {
        $message->to('a@b.c')->subject('second');
    });

    $requests = [];
    Http::assertSent(function (Request $request) use (&$requests): bool {
        $requests[] = $request->header('X-Server-API-Key')[0] ?? null;

        return true;
    });

    expect($requests)->toBe(['test-api-key', 'rotated-key']);
});
