<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Facades\Postal;
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

it('receives a really-signed MessageSent webhook and processes it through the full spine', function (): void {
    $subject = 'E2E webhook '.uniqid();

    // 1. The live signing key, exactly as postal:webhook-key would fetch it.
    $jwks = Http::get(config('postal.servers.default.url').'/.well-known/jwks.json')->json('keys.0');
    expect($jwks)->toBeArray();

    $pem = JwkConverter::toPem($jwks);

    // 2. Send a message; the relay accepts it, Postal dispatches a signed
    //    MessageSent webhook to the capture server.
    $result = Postal::send(
        SendMessage::create()
            ->to('webhook-target@sink.example.com')
            ->from('e2e@e2e.example.com')
            ->subject($subject)
            ->plain('Trigger a webhook'),
    );

    $postalId = $result->first()->id;

    $delivery = E2E::waitFor(function () use ($postalId): ?array {
        foreach (E2E::captured('capture_webhook') as $record) {
            $body = json_decode($record['body'], true);

            if (is_array($body)
                && ($body['event'] ?? null) === 'MessageSent'
                && (int) data_get($body, 'payload.message.id') === $postalId) {
                return $record;
            }
        }

        return null;
    }, "a signed MessageSent webhook for message {$postalId}", 90);

    // 3. Verify Postal's real signature over the captured raw bytes.
    $verifier = new RsaSignatureVerifier($pem);
    $signature256 = $delivery['headers']['X-POSTAL-SIGNATURE-256'] ?? null;
    $signatureSha1 = $delivery['headers']['X-POSTAL-SIGNATURE'] ?? null;

    expect($signature256)->not->toBeNull('Postal 3.x must send X-Postal-Signature-256')
        ->and($verifier->verify($delivery['body'], $signature256, $signatureSha1))->toBeTrue()
        ->and($verifier->verify($delivery['body'].'tampered', $signature256, $signatureSha1))->toBeFalse();

    // 4. Replay the exact bytes through the package's own webhook route —
    //    controller, verifier, queued job (sync), store, typed event.
    config()->set('postal.webhooks.public_key', $pem);

    Event::fake([PostalMessageSent::class]);

    $this->call('POST', '/postal/webhook', [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_POSTAL_SIGNATURE_256' => $signature256,
        'HTTP_X_POSTAL_SIGNATURE' => $signatureSha1,
    ]), $delivery['body'])->assertOk();

    Event::assertDispatched(PostalMessageSent::class, function (PostalMessageSent $event) use ($postalId, $subject): bool {
        return $event->payload->message->id === $postalId
            && $event->payload->message->subject === $subject
            && $event->payload->status === 'Sent';
    });

    $row = PostalMessage::query()->where('postal_message_id', $postalId)->sole();

    expect($row->status)->toBe('Sent')
        ->and($row->subject)->toBe($subject)
        ->and($row->last_event)->toBe('MessageSent');
});
