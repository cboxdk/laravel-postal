<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The result of a structured or raw send: the RFC message id Postal assigned
 * plus one {id, token} pair per accepted recipient.
 */
readonly class SendResult
{
    /**
     * @param  array<string, SendRecipient>  $recipients  Keyed by recipient address.
     */
    public function __construct(
        public ?string $messageId,
        public array $recipients,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The `data` object of a send envelope.
     */
    public static function fromArray(array $data): self
    {
        $recipients = [];

        foreach (Coerce::map($data['messages'] ?? null) as $address => $message) {
            if (! is_array($message)) {
                continue;
            }

            $message = Coerce::map($message);

            $recipients[$address] = new SendRecipient(
                address: $address,
                id: Coerce::int($message['id'] ?? null),
                token: Coerce::string($message['token'] ?? null),
            );
        }

        return new self(
            messageId: Coerce::stringOrNull($data['message_id'] ?? null),
            recipients: $recipients,
        );
    }

    public function first(): ?SendRecipient
    {
        foreach ($this->recipients as $recipient) {
            return $recipient;
        }

        return null;
    }

    /**
     * Postal's internal message ids, one per recipient.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(
            static fn (SendRecipient $recipient): int => $recipient->id,
            $this->recipients,
        ));
    }

    public function recipient(string $address): ?SendRecipient
    {
        return $this->recipients[$address] ?? null;
    }
}
