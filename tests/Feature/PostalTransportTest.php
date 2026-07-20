<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config()->set('mail.default', 'postal');
    config()->set('mail.mailers.postal', ['transport' => 'postal']);
    config()->set('mail.from', ['address' => 'no-reply@cboxid.com', 'name' => 'Cbox']);
});

function fakeRawSend(): void
{
    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response([
            'status' => 'success',
            'time' => 0.1,
            'flags' => [],
            'data' => [
                'message_id' => 'postal-mid-77@postal.test',
                'messages' => ['alice@example.com' => ['id' => 77, 'token' => 'tok77']],
            ],
        ]),
    ]);
}

it('sends rendered mail through /send/raw', function (): void {
    fakeRawSend();

    Mail::raw('Hello from the transport', function (Message $message): void {
        $message->to('alice@example.com')->subject('Transport test');
    });

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://postal.test/api/v1/send/raw') {
            return false;
        }

        $data = $request['data'];
        $mime = is_string($data) ? base64_decode($data, true) : false;

        return $request['mail_from'] === 'no-reply@cboxid.com'
            && $request['rcpt_to'] === ['alice@example.com']
            && is_string($mime)
            && str_contains($mime, 'Subject: Transport test')
            && str_contains($mime, 'Hello from the transport');
    });
});

it('stamps the Postal message id onto the sent message and headers', function (): void {
    fakeRawSend();

    $sent = Mail::to('alice@example.com')->send(new class extends Mailable
    {
        public function build(): self
        {
            return $this->subject('Stamped')->html('<p>Hi</p>');
        }
    });

    expect($sent)->not->toBeNull()
        ->and($sent->getMessageId())->toBe('postal-mid-77@postal.test')
        ->and($sent->getOriginalMessage()->getHeaders()->get('X-Postal-Message-Id')?->getBodyAsString())
        ->toBe('postal-mid-77@postal.test');
});

it('redirects all recipients when postal.redirect_to is set', function (): void {
    config()->set('postal.redirect_to', 'dev@cbox.dk');

    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response([
            'status' => 'success',
            'time' => 0.1,
            'flags' => [],
            'data' => [
                'message_id' => 'redirected@postal.test',
                'messages' => ['dev@cbox.dk' => ['id' => 78, 'token' => 't']],
            ],
        ]),
    ]);

    Mail::raw('Redirected body', function (Message $message): void {
        $message->to('alice@example.com')->cc('bob@example.com')->subject('Redirect');
    });

    Http::assertSent(fn (Request $request): bool => $request['rcpt_to'] === ['dev@cbox.dk']);
});

it('uses the server named in the mailer config', function (): void {
    config()->set('mail.mailers.postal', ['transport' => 'postal', 'server' => 'second']);

    Http::fake([
        'postal-second.test/api/v1/send/raw' => Http::response([
            'status' => 'success',
            'time' => 0.1,
            'flags' => [],
            'data' => ['message_id' => 'second@postal.test', 'messages' => []],
        ]),
    ]);

    Mail::raw('Second server', function (Message $message): void {
        $message->to('alice@example.com')->subject('Second');
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://postal-second.test/api/v1/send/raw'
            && $request->hasHeader('X-Server-API-Key', 'second-api-key');
    });
});
