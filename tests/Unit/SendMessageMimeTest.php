<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\Attachment;
use Cbox\LaravelPostal\Dto\SendMessage;

it('renders a MIME message with headers, tag and attachments', function (): void {
    $email = SendMessage::create()
        ->to('alice@example.com')
        ->cc('bob@example.com')
        ->from('Cbox <no-reply@cboxid.com>')
        ->replyTo('support@cboxid.com')
        ->subject('Mime test')
        ->tag('onboarding')
        ->header('X-Custom', 'value')
        ->plain('Hello plain')
        ->html('<p>Hello html</p>')
        ->attach(new Attachment('report.txt', 'text/plain', 'file-content'))
        ->toEmail();

    $mime = $email->toString();

    expect($email->getSubject())->toBe('Mime test')
        ->and($email->getTextBody())->toBe('Hello plain')
        ->and($email->getHtmlBody())->toBe('<p>Hello html</p>')
        ->and($email->getHeaders()->get('X-Postal-Tag')?->getBodyAsString())->toBe('onboarding')
        ->and($email->getHeaders()->get('X-Custom')?->getBodyAsString())->toBe('value')
        ->and($mime)->toContain('report.txt');
});

it('strips display names for envelope addresses', function (): void {
    $message = SendMessage::create()
        ->to('Alice <alice@example.com>')
        ->bcc('carol@example.com')
        ->from('Cbox <no-reply@cboxid.com>');

    expect($message->envelopeFrom())->toBe('no-reply@cboxid.com')
        ->and($message->envelopeRecipients())->toBe(['alice@example.com', 'carol@example.com']);
});

it('requires a from address to render MIME', function (): void {
    SendMessage::create()->to('a@b.c')->toEmail();
})->throws(LogicException::class);
