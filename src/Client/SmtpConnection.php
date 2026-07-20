<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendResult;
use Cbox\LaravelPostal\Exceptions\ServerException;
use Cbox\LaravelPostal\Exceptions\UnsupportedOperationException;
use Cbox\LaravelPostal\Support\ServerConfig;
use Cbox\LaravelPostal\Support\SmtpConfig;
use LogicException;
use Symfony\Component\Mailer\Envelope as MailerEnvelope;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Throwable;

/**
 * A connection that submits mail over classic SMTP (Postal SMTP / SMTP-IP
 * credentials). When the server also has an API URL + key configured, read
 * operations (message, deliveries) transparently use the API; otherwise
 * they raise UnsupportedOperationException.
 *
 * SMTP acceptance returns no Postal message ids — the SendResult carries
 * the MIME Message-ID and no per-recipient map; correlation happens through
 * the webhook spine.
 */
class SmtpConnection implements Connection
{
    private readonly SmtpConfig $smtp;

    private ?TransportInterface $transport;

    private readonly ?PostalClient $api;

    public function __construct(
        private readonly ServerConfig $server,
        ?PostalClient $api = null,
        ?TransportInterface $transport = null,
    ) {
        $this->smtp = $server->smtp ?? throw new LogicException(
            "Postal server [{$server->name}] has no smtp settings.",
        );
        $this->api = $api;
        $this->transport = $transport;
    }

    public function name(): string
    {
        return $this->server->name;
    }

    public function send(SendMessage $message): SendResult
    {
        if ($message->isBounce()) {
            throw new UnsupportedOperationException(
                'The bounce flag cannot be transmitted over SMTP submission — use an api or smtp-api connection for bounce sends.',
            );
        }

        $from = $message->envelopeFrom();

        if ($from === null) {
            throw new LogicException('A from address is required to send over SMTP.');
        }

        // Render once and submit those exact bytes — the generated
        // Message-ID can then be reported back reliably.
        return $this->submit($message->toEmail()->toString(), $from, $message->envelopeRecipients());
    }

    public function sendRaw(string $mailFrom, array $rcptTo, string $rawMessage, bool $bounce = false): SendResult
    {
        if ($bounce) {
            // The bounce flag is an HTTP-API concept; plain SMTP submission
            // cannot signal it, so failing loudly beats silently dropping it.
            throw new UnsupportedOperationException(
                'The bounce flag cannot be transmitted over SMTP submission — use an api or smtp-api connection for bounce sends.',
            );
        }

        return $this->submit($rawMessage, $mailFrom, $rcptTo);
    }

    public function message(int $id, bool|array $expansions = true): MessageDetails
    {
        return $this->apiOr('message lookups')->message($id, $expansions);
    }

    public function deliveries(int $id): array
    {
        return $this->apiOr('delivery lookups')->deliveries($id);
    }

    /**
     * SMTP ping: open (EHLO) and close a connection to the submission port.
     */
    public function ping(): ConnectionStatus
    {
        $start = hrtime(true);

        try {
            $transport = $this->transport();

            if ($transport instanceof EsmtpTransport) {
                $transport->start();
                $transport->stop();
            }

            $ok = true;
            $error = null;
        } catch (Throwable $exception) {
            $ok = false;
            $error = $exception->getMessage();
        }

        return new ConnectionStatus(
            server: $this->server->name,
            url: "smtp://{$this->smtp->host}:{$this->smtp->port}",
            ok: $ok,
            roundTripMs: round((hrtime(true) - $start) / 1_000_000, 1),
            error: $error,
        );
    }

    /**
     * @param  list<string>  $recipients
     */
    private function submit(string $rawMessage, string $from, array $recipients): SendResult
    {
        if ($recipients === []) {
            throw new LogicException('At least one recipient is required to send over SMTP.');
        }

        $envelope = new MailerEnvelope(
            new Address($from),
            array_map(static fn (string $recipient): Address => new Address($recipient), $recipients),
        );

        try {
            $this->transport()->send(new RawMessage($rawMessage), $envelope);
        } catch (Throwable $exception) {
            throw new ServerException(
                "SMTP submission to [{$this->smtp->host}:{$this->smtp->port}] failed: {$exception->getMessage()}",
            );
        }

        return new SendResult(
            messageId: self::messageIdFrom($rawMessage),
            recipients: [],
        );
    }

    /**
     * The Message-ID of the submitted MIME, when its header block declares
     * one. Only the headers are scanned — a forwarded message embedded in
     * the body must never be mistaken for this message's id.
     */
    private static function messageIdFrom(string $rawMessage): ?string
    {
        $headerEnd = strpos($rawMessage, "\r\n\r\n");
        $headers = $headerEnd === false ? $rawMessage : substr($rawMessage, 0, $headerEnd);

        return preg_match('/^Message-ID:\s*<([^>]+)>/im', $headers, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function transport(): TransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }

        $transport = new EsmtpTransport($this->smtp->host, $this->smtp->port, $this->smtp->tls);

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout($this->smtp->timeout);
        }

        if ($this->smtp->username !== null) {
            $transport->setUsername($this->smtp->username);
        }

        if ($this->smtp->password !== null) {
            $transport->setPassword($this->smtp->password);
        }

        return $this->transport = $transport;
    }

    private function apiOr(string $operation): PostalClient
    {
        return $this->api ?? throw new UnsupportedOperationException(
            "Postal server [{$this->server->name}] submits over SMTP and has no API URL/key — {$operation} are unavailable.",
        );
    }
}
