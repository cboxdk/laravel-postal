<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Exceptions\AuthenticationException;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Exceptions\RateLimitException;
use Cbox\LaravelPostal\Exceptions\ServerException;
use Cbox\LaravelPostal\Exceptions\ValidationException;
use Cbox\LaravelPostal\Facades\Postal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function successEnvelope(array $data): array
{
    return ['status' => 'success', 'time' => 0.1, 'flags' => [], 'data' => $data];
}

function errorEnvelope(array $data): array
{
    return ['status' => 'error', 'time' => 0.1, 'flags' => [], 'data' => $data];
}

it('sends a structured message and returns a typed result', function (): void {
    Http::fake([
        'postal.test/api/v1/send/message' => Http::response(successEnvelope([
            'message_id' => 'msgid-1@postal.test',
            'messages' => [
                'alice@example.com' => ['id' => 101, 'token' => 'tokenA'],
                'bob@example.com' => ['id' => 102, 'token' => 'tokenB'],
            ],
        ])),
    ]);

    $result = Postal::send(
        SendMessage::create()
            ->to(['alice@example.com', 'bob@example.com'])
            ->from('no-reply@cboxid.com')
            ->subject('Hi'),
    );

    expect($result->messageId)->toBe('msgid-1@postal.test')
        ->and($result->ids())->toBe([101, 102])
        ->and($result->recipient('alice@example.com')?->token)->toBe('tokenA')
        ->and($result->first()?->id)->toBe(101);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://postal.test/api/v1/send/message'
            && $request->hasHeader('X-Server-API-Key', 'test-api-key')
            && $request['to'] === ['alice@example.com', 'bob@example.com']
            && $request['subject'] === 'Hi';
    });
});

it('maps an HTTP 200 error envelope to an authentication exception', function (): void {
    Http::fake([
        'postal.test/*' => Http::response(errorEnvelope([
            'code' => 'InvalidServerAPIKey',
            'message' => 'The API token provided in X-Server-API-Key was not valid.',
        ]), 200),
    ]);

    try {
        Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $exception) {
        expect($exception->errorCode)->toBe('InvalidServerAPIKey')
            ->and($exception->getMessage())->toContain('X-Server-API-Key');
    }
});

it('maps send validation error codes to a validation exception', function (): void {
    Http::fake([
        'postal.test/*' => Http::response(errorEnvelope([
            'code' => 'NoRecipients',
            'message' => 'There are no recipients defined to receive this message',
        ])),
    ]);

    Postal::send(SendMessage::create()->from('x@y.z'));
})->throws(ValidationException::class, 'no recipients');

it('maps a parameter-error status to a validation exception', function (): void {
    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'parameter-error',
            'time' => 0.1,
            'flags' => [],
            'data' => ['message' => '`rcpt_to` parameter is required but is missing'],
        ]),
    ]);

    Postal::sendRaw('x@y.z', [], 'raw');
})->throws(ValidationException::class, 'rcpt_to');

it('throws a rate limit exception on HTTP 429', function (): void {
    Http::fake(['postal.test/*' => Http::response('slow down', 429)]);

    Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));
})->throws(RateLimitException::class);

it('throws a server exception on persistent 5xx', function (): void {
    Http::fake(['postal.test/*' => Http::response('boom', 500)]);

    Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));
})->throws(ServerException::class);

it('retries 5xx responses and succeeds when the server recovers', function (): void {
    Http::fake([
        'postal.test/*' => Http::sequence()
            ->push('boom', 500)
            ->push(successEnvelope(['message_id' => 'ok@postal', 'messages' => []])),
    ]);

    $result = Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));

    expect($result->messageId)->toBe('ok@postal');
    Http::assertSentCount(2);
});

it('does not retry error envelopes', function (): void {
    Http::fake([
        'postal.test/*' => Http::response(errorEnvelope(['code' => 'NoContent', 'message' => 'no content'])),
    ]);

    try {
        Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z'));
    } catch (ValidationException) {
        // expected
    }

    Http::assertSentCount(1);
});

it('sends raw messages base64 encoded', function (): void {
    Http::fake([
        'postal.test/api/v1/send/raw' => Http::response(successEnvelope([
            'message_id' => 'raw@postal',
            'messages' => ['a@b.c' => ['id' => 7, 'token' => 't']],
        ])),
    ]);

    $result = Postal::sendRaw('x@y.z', ['a@b.c'], "From: x@y.z\r\n\r\nBody");

    expect($result->recipient('a@b.c')?->id)->toBe(7);

    Http::assertSent(function (Request $request): bool {
        return $request['mail_from'] === 'x@y.z'
            && $request['rcpt_to'] === ['a@b.c']
            && $request['data'] === base64_encode("From: x@y.z\r\n\r\nBody");
    });
});

it('fetches message details with expansions', function (): void {
    Http::fake([
        'postal.test/api/v1/messages/message' => Http::response(successEnvelope([
            'id' => 55,
            'token' => 'tok55',
            'status' => ['status' => 'Sent', 'last_delivery_attempt' => 1700000000.5, 'held' => false, 'hold_expiry' => null],
            'details' => [
                'rcpt_to' => 'a@b.c',
                'mail_from' => 'x@y.z',
                'subject' => 'Hi',
                'message_id' => 'mid@postal',
                'timestamp' => 1700000000.1,
                'direction' => 'outgoing',
                'size' => 1234,
                'bounce' => false,
                'bounce_for_id' => 0,
                'tag' => 'onboarding',
                'received_with_ssl' => true,
            ],
            'inspection' => ['inspected' => true, 'spam' => false, 'spam_score' => 0.2, 'threat' => false, 'threat_details' => null],
            'plain_body' => 'Hello',
            'html_body' => '<p>Hello</p>',
        ])),
    ]);

    $message = Postal::message(55);

    expect($message->id)->toBe(55)
        ->and($message->token)->toBe('tok55')
        ->and($message->status?->status)->toBe('Sent')
        ->and($message->details?->rcptTo)->toBe('a@b.c')
        ->and($message->details?->tag)->toBe('onboarding')
        ->and($message->inspection?->spamScore)->toBe(0.2)
        ->and($message->plainBody)->toBe('Hello');

    Http::assertSent(fn (Request $request): bool => $request['id'] === 55 && $request['_expansions'] === true);
});

it('maps MessageNotFound to its own exception', function (): void {
    Http::fake([
        'postal.test/*' => Http::response(errorEnvelope([
            'code' => 'MessageNotFound',
            'message' => 'No message found matching provided ID',
            'id' => 999,
        ])),
    ]);

    Postal::message(999);
})->throws(MessageNotFoundException::class);

it('fetches delivery attempts', function (): void {
    Http::fake([
        'postal.test/api/v1/messages/deliveries' => Http::response([
            'status' => 'success',
            'time' => 0.1,
            'flags' => [],
            'data' => [[
                'id' => 1,
                'status' => 'Sent',
                'details' => 'Message sent to mx.example.com',
                'output' => '250 OK',
                'sent_with_ssl' => true,
                'log_id' => 'ABC123',
                'time' => 1.2,
                'timestamp' => 1700000001.0,
            ]],
        ]),
    ]);

    $deliveries = Postal::deliveries(55);

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]->status)->toBe('Sent')
        ->and($deliveries[0]->output)->toBe('250 OK')
        ->and($deliveries[0]->sentWithSsl)->toBeTrue();
});
