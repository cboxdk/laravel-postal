<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Client\SmtpConnection;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Exceptions\ServerException;
use Cbox\LaravelPostal\Exceptions\UnsupportedOperationException;
use Cbox\LaravelPostal\Support\ServerConfig;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

class RecordingTransport implements TransportInterface
{
    /** @var list<array{message: RawMessage, envelope: Envelope}> */
    public array $sent = [];

    public bool $fail = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->fail) {
            throw new RuntimeException('connection refused');
        }

        if ($envelope === null) {
            throw new RuntimeException('expected an explicit envelope');
        }

        $this->sent[] = ['message' => $message, 'envelope' => $envelope];

        $sent = new SentMessage($message, $envelope);
        $sent->setMessageId('smtp-mid@postal.test');

        return $sent;
    }

    public function __toString(): string
    {
        return 'recording://';
    }
}

function smtpServer(): ServerConfig
{
    return ServerConfig::fromArray('smtp-server', [
        'type' => 'smtp',
        'smtp' => ['host' => 'postal.test', 'port' => 2525],
    ]);
}

it('submits a structured message as MIME over SMTP', function (): void {
    $transport = new RecordingTransport;
    $connection = new SmtpConnection(smtpServer(), null, $transport);

    $result = $connection->send(
        SendMessage::create()
            ->to('Alice <alice@example.com>')
            ->from('Cbox <no-reply@cboxid.com>')
            ->subject('Over SMTP')
            ->plain('Hi'),
    );

    expect($result->messageId)->toContain('@')
        ->and($result->recipients)->toBe([])
        ->and($transport->sent)->toHaveCount(1);

    $envelope = $transport->sent[0]['envelope'];

    expect($envelope->getSender()->getAddress())->toBe('no-reply@cboxid.com')
        ->and(array_map(fn (Address $a): string => $a->getAddress(), $envelope->getRecipients()))
        ->toBe(['alice@example.com'])
        ->and($transport->sent[0]['message']->toString())->toContain('Subject: Over SMTP');
});

it('submits raw messages over SMTP', function (): void {
    $transport = new RecordingTransport;
    $connection = new SmtpConnection(smtpServer(), null, $transport);

    $raw = "From: x@y.z\r\nSubject: Raw\r\n\r\nBody";
    $connection->sendRaw('x@y.z', ['a@b.c'], $raw);

    expect($transport->sent[0]['message']->toString())->toBe($raw);
});

it('wraps transport failures in a server exception', function (): void {
    $transport = new RecordingTransport;
    $transport->fail = true;

    $connection = new SmtpConnection(smtpServer(), null, $transport);

    $connection->sendRaw('x@y.z', ['a@b.c'], 'raw');
})->throws(ServerException::class, 'SMTP submission');

it('refuses lookups without api credentials', function (): void {
    $connection = new SmtpConnection(smtpServer(), null, new RecordingTransport);

    $connection->message(1);
})->throws(UnsupportedOperationException::class, 'unavailable');

it('requires at least one recipient', function (): void {
    $connection = new SmtpConnection(smtpServer(), null, new RecordingTransport);

    $connection->sendRaw('x@y.z', [], 'raw');
})->throws(LogicException::class, 'recipient');
