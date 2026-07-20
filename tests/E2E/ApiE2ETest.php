<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\MessageExpansion;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Exceptions\AuthenticationException;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Exceptions\ValidationException;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\Support\ServerConfig;
use Cbox\LaravelPostal\Tests\E2E\E2E;

beforeEach(function (): void {
    if (! E2E::enabled()) {
        $this->markTestSkipped('POSTAL_E2E_* env vars not set — run via e2e/run.sh');
    }

    E2E::configure($this->app);
});

it('pings the real install with a valid key', function (): void {
    $status = Postal::ping();

    expect($status->ok)->toBeTrue()
        ->and($status->roundTripMs)->toBeGreaterThan(0.0);
});

it('maps a bad API key to an AuthenticationException against the real API', function (): void {
    $connection = Postal::connect(new ServerConfig(
        name: 'bad-key',
        url: config('postal.servers.default.url'),
        key: 'definitely-not-a-valid-key',
    ));

    expect($connection->ping()->ok)->toBeFalse();

    $connection->send(SendMessage::create()->to('a@e2e.example.com')->from('x@e2e.example.com'));
})->throws(AuthenticationException::class);

it('maps MessageNotFound from the real API', function (): void {
    Postal::message(999999999);
})->throws(MessageNotFoundException::class);

it('maps real send validation errors', function (): void {
    // No recipients — the real API answers HTTP 200 + error envelope.
    Postal::send(SendMessage::create()->from('e2e@e2e.example.com')->plain('x'));
})->throws(ValidationException::class);

it('sends a structured message and reads it back with typed expansions', function (): void {
    $result = Postal::send(
        SendMessage::create()
            ->to('recipient@sink.example.com')
            ->from('E2E <e2e@e2e.example.com>')
            ->subject('E2E structured '.uniqid())
            ->tag('e2e')
            ->plain('Structured body')
            ->html('<p>Structured body</p>'),
    );

    expect($result->messageId)->not->toBeNull()
        ->and($result->recipients)->toHaveCount(1);

    $id = $result->first()->id;

    $message = Postal::message($id, [MessageExpansion::Status, MessageExpansion::Details, MessageExpansion::PlainBody]);

    expect($message->id)->toBe($id)
        ->and($message->details?->rcptTo)->toBe('recipient@sink.example.com')
        ->and($message->details->tag)->toBe('e2e')
        ->and($message->plainBody)->toContain('Structured body')
        ->and($message->inspection)->toBeNull();

    // The relay (mailpit) accepts everything, so the delivery must land.
    E2E::waitFor(function () use ($id): ?true {
        $deliveries = Postal::deliveries($id);

        foreach ($deliveries as $delivery) {
            if ($delivery->status === 'Sent') {
                return true;
            }
        }

        return null;
    }, "message {$id} to be delivered via the relay");
});

it('sends raw RFC 2822 through the API and through an smtp-api connection', function (): void {
    $raw = SendMessage::create()
        ->to('raw@sink.example.com')
        ->from('e2e@e2e.example.com')
        ->subject('E2E raw '.uniqid())
        ->plain('Raw body')
        ->toEmail()
        ->toString();

    $direct = Postal::sendRaw('e2e@e2e.example.com', ['raw@sink.example.com'], $raw);

    expect($direct->recipients)->toHaveCount(1);

    $viaType = Postal::server('raw')->send(
        SendMessage::create()
            ->to('raw-type@sink.example.com')
            ->from('e2e@e2e.example.com')
            ->subject('E2E smtp-api '.uniqid())
            ->plain('Via smtp-api type'),
    );

    expect($viaType->recipients)->toHaveCount(1)
        ->and($viaType->first()->id)->toBeGreaterThan(0);
});

it('rejects raw sends from unauthorised domains with a typed exception', function (): void {
    Postal::sendRaw('intruder@not-our-domain.example', ['a@sink.example.com'], "From: intruder@not-our-domain.example\r\nSubject: x\r\n\r\nbody");
})->throws(ValidationException::class);
