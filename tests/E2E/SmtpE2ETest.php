<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\Tests\E2E\E2E;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    if (! E2E::enabled()) {
        $this->markTestSkipped('POSTAL_E2E_* env vars not set — run via e2e/run.sh');
    }

    E2E::configure($this->app);
});

it('performs a real SMTP handshake ping', function (): void {
    $status = Postal::server('smtp')->ping();

    expect($status->ok)->toBeTrue()
        ->and($status->url)->toStartWith('smtp://');
});

it('submits mail over authenticated SMTP and it reaches the relay sink', function (): void {
    $subject = 'E2E via SMTP '.uniqid();

    $result = Postal::server('smtp')->send(
        SendMessage::create()
            ->to('smtp-recipient@sink.example.com')
            ->from('e2e@e2e.example.com')
            ->subject($subject)
            ->plain('Submitted over SMTP'),
    );

    // SMTP acceptance yields a MIME message id, not Postal ids.
    expect($result->messageId)->not->toBeNull()
        ->and($result->recipients)->toBe([]);

    // Postal's worker relays outbound to mailpit — poll its API until the
    // message shows up there.
    E2E::waitFor(function () use ($subject): ?true {
        $response = Http::get(E2E::mailpitUrl().'/api/v1/search', ['query' => "subject:\"{$subject}\""]);

        $count = $response->json('messages_count');

        return is_int($count) && $count > 0 ? true : null;
    }, 'the SMTP-submitted message to reach the relay sink');
});

it('still supports API lookups on the smtp connection because url+key are configured', function (): void {
    expect(fn () => Postal::server('smtp')->message(999999999))
        ->toThrow(MessageNotFoundException::class);
});
