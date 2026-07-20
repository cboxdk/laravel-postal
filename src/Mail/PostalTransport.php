<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Mail;

use Cbox\LaravelPostal\Contracts\Factory;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;

/**
 * The `postal` mail transport: hands the rendered MIME message to the
 * configured server connection (raw HTTP API or SMTP depending on its
 * type) and stamps the Postal message id back onto the sent message and an
 * X-Postal-Message-Id header so MessageSent listeners and the webhook
 * store can correlate.
 *
 * The connection is resolved from the factory per send — never cached
 * here — so credential rotation via Postal::forget() takes effect even in
 * long-lived processes (Octane, queue workers) with cached mailers.
 */
class PostalTransport extends AbstractTransport
{
    public function __construct(
        private readonly Factory $postal,
        private readonly ?string $server = null,
        private readonly ?string $redirectTo = null,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $envelope = $message->getEnvelope();

        $recipients = $this->redirectTo !== null && $this->redirectTo !== ''
            ? [$this->redirectTo]
            : array_map(
                static fn (Address $address): string => $address->getAddress(),
                $envelope->getRecipients(),
            );

        $result = $this->postal->server($this->server)->sendRaw(
            $envelope->getSender()->getAddress(),
            array_values($recipients),
            $message->toString(),
        );

        if ($result->messageId !== null) {
            $message->setMessageId($result->messageId);

            $original = $message->getOriginalMessage();

            if ($original instanceof Message) {
                $original->getHeaders()->addTextHeader('X-Postal-Message-Id', $result->messageId);
            }
        }
    }

    public function __toString(): string
    {
        return 'postal://'.($this->server ?? 'default');
    }
}
