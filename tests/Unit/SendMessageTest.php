<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\Attachment;
use Cbox\LaravelPostal\Dto\SendMessage;

it('renders the minimal send payload', function (): void {
    $payload = SendMessage::create()
        ->to('alice@example.com')
        ->from('Cbox <no-reply@cboxid.com>')
        ->toArray();

    expect($payload)->toBe([
        'to' => ['alice@example.com'],
        'from' => 'Cbox <no-reply@cboxid.com>',
    ]);
});

it('renders a full send payload', function (): void {
    $payload = SendMessage::create()
        ->to(['alice@example.com', 'bob@example.com'])
        ->cc('carol@example.com')
        ->bcc('dave@example.com')
        ->from('no-reply@cboxid.com')
        ->sender('sender@cboxid.com')
        ->replyTo('support@cboxid.com')
        ->subject('Welcome')
        ->tag('onboarding')
        ->plain('Hello')
        ->html('<p>Hello</p>')
        ->header('X-Custom', 'value')
        ->attach(new Attachment('report.txt', 'text/plain', 'hello world'))
        ->bounce()
        ->toArray();

    expect($payload['to'])->toBe(['alice@example.com', 'bob@example.com'])
        ->and($payload['cc'])->toBe(['carol@example.com'])
        ->and($payload['bcc'])->toBe(['dave@example.com'])
        ->and($payload['sender'])->toBe('sender@cboxid.com')
        ->and($payload['reply_to'])->toBe('support@cboxid.com')
        ->and($payload['subject'])->toBe('Welcome')
        ->and($payload['tag'])->toBe('onboarding')
        ->and($payload['plain_body'])->toBe('Hello')
        ->and($payload['html_body'])->toBe('<p>Hello</p>')
        ->and($payload['headers'])->toBe(['X-Custom' => 'value'])
        ->and($payload['bounce'])->toBeTrue()
        ->and($payload['attachments'])->toBe([[
            'name' => 'report.txt',
            'content_type' => 'text/plain',
            'data' => base64_encode('hello world'),
        ]]);
});

it('accumulates repeated to() calls', function (): void {
    $payload = SendMessage::create()
        ->to('alice@example.com')
        ->to('bob@example.com')
        ->from('no-reply@cboxid.com')
        ->toArray();

    expect($payload['to'])->toBe(['alice@example.com', 'bob@example.com']);
});

it('omits optional fields that are not set', function (): void {
    $payload = SendMessage::create()
        ->to('alice@example.com')
        ->from('no-reply@cboxid.com')
        ->toArray();

    expect($payload)->not->toHaveKeys(['cc', 'bcc', 'subject', 'tag', 'headers', 'attachments', 'bounce']);
});
