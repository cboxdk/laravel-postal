<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Client\SmtpConnection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Facades\Postal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('routes send() through /send/raw on an smtp-api server', function (): void {
    config()->set('postal.servers.rawish', [
        'url' => 'https://postal.test',
        'key' => 'test-api-key',
        'type' => 'smtp-api',
    ]);

    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['message_id' => 'raw-mid@postal.test', 'messages' => ['alice@example.com' => ['id' => 5, 'token' => 't']]],
        ]),
    ]);

    $result = Postal::server('rawish')->send(
        SendMessage::create()
            ->to('Alice <alice@example.com>')
            ->from('Cbox <no-reply@cboxid.com>')
            ->subject('Raw over API')
            ->tag('onboarding')
            ->plain('Hi'),
    );

    expect($result->messageId)->toBe('raw-mid@postal.test');

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://postal.test/api/v1/send/raw') {
            return false;
        }

        $data = $request['data'];
        $mime = is_string($data) ? base64_decode($data, true) : false;

        return $request['mail_from'] === 'no-reply@cboxid.com'
            && $request['rcpt_to'] === ['alice@example.com']
            && is_string($mime)
            && str_contains($mime, 'Subject: Raw over API')
            && str_contains($mime, 'X-Postal-Tag: onboarding');
    });
});

it('builds an smtp connection from config and pings over smtp', function (): void {
    config()->set('postal.servers.smtp-only', [
        'type' => 'smtp',
        'smtp' => ['host' => '127.0.0.1', 'port' => 1, 'timeout' => 2],
    ]);

    $connection = $this->app->make(Factory::class)->server('smtp-only');

    expect($connection)->toBeInstanceOf(SmtpConnection::class);

    // Port 1 on localhost refuses immediately — a real failed handshake.
    $status = $connection->ping();

    expect($status->ok)->toBeFalse()
        ->and($status->url)->toBe('smtp://127.0.0.1:1');
});
