<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\Inbound\InboundMessage;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Support\JwkConverter;
use Cbox\LaravelPostal\Tests\E2E\E2E;
use Cbox\LaravelPostal\Webhooks\RsaSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! E2E::enabled()) {
        $this->markTestSkipped('POSTAL_E2E_* env vars not set — run via e2e/run.sh');
    }

    E2E::configure($this->app);
});

it('receives inbound mail via SMTP → route → signed HTTP endpoint and processes it end to end', function (): void {
    $subject = 'E2E inbound '.uniqid();

    // 1. Deliver a message INTO Postal the way the outside world does:
    //    unauthenticated SMTP to a routed domain.
    $raw = "From: Outside World <outside@sender.example.org>\r\n"
        ."To: support@inbound.e2e.example.com\r\n"
        ."Subject: {$subject}\r\n"
        .'Message-ID: <'.uniqid()."@sender.example.org>\r\n"
        ."\r\n"
        .'Hello from outside — please file a ticket.';

    Postal::server('smtp-inbound')->sendRaw(
        'outside@sender.example.org',
        ['support@inbound.e2e.example.com'],
        $raw,
    );

    // 2. Postal routes it to the HTTP endpoint; the capture server records
    //    the signed delivery.
    $delivery = E2E::waitFor(function () use ($subject): ?array {
        foreach (E2E::captured('capture_inbound') as $record) {
            $body = json_decode($record['body'], true);

            if (is_array($body) && ($body['subject'] ?? null) === $subject) {
                return $record;
            }
        }

        return null;
    }, 'the signed inbound HTTP-endpoint delivery', 90);

    // 3. The inbound delivery is signed with the same install key.
    $jwks = Http::get(config('postal.servers.default.url').'/.well-known/jwks.json')->json('keys.0');
    $pem = JwkConverter::toPem($jwks);

    $verifier = new RsaSignatureVerifier($pem);
    $signature256 = $delivery['headers']['X-POSTAL-SIGNATURE-256'] ?? null;

    expect($verifier->verify($delivery['body'], $signature256, $delivery['headers']['X-POSTAL-SIGNATURE'] ?? null))->toBeTrue();

    // 4. The DTO parses the real payload shape.
    $parsed = InboundMessage::fromArray(json_decode($delivery['body'], true));

    expect($parsed->subject)->toBe($subject)
        ->and($parsed->rcptTo)->toBe('support@inbound.e2e.example.com')
        ->and($parsed->mailFrom)->toBe('outside@sender.example.org')
        ->and($parsed->plainBody)->toContain('file a ticket')
        ->and($parsed->id)->toBeGreaterThan(0);

    // 5. Replay the exact bytes through the package's inbound route.
    config()->set('postal.webhooks.public_key', $pem);

    Event::fake([PostalInboundMessage::class]);

    $this->call('POST', '/postal/inbound', [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_POSTAL_SIGNATURE_256' => $signature256,
        'HTTP_X_POSTAL_SIGNATURE' => $delivery['headers']['X-POSTAL-SIGNATURE'] ?? null,
    ]), $delivery['body'])->assertOk();

    Event::assertDispatched(PostalInboundMessage::class, function (PostalInboundMessage $event) use ($subject): bool {
        return $event->payload->subject === $subject
            && $event->uuid() !== null;
    });

    $row = PostalMessage::query()->where('postal_message_id', $parsed->id)->sole();

    expect($row->direction)->toBe('incoming')
        ->and($row->status)->toBe('Received')
        ->and($row->to)->toBe('support@inbound.e2e.example.com');
});
