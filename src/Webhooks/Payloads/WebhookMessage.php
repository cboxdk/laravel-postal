<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `message` hash Postal embeds in message-bearing webhook payloads.
 */
readonly class WebhookMessage
{
    public function __construct(
        public int $id,
        public ?string $token,
        public ?string $direction,
        public ?string $messageId,
        public ?string $to,
        public ?string $from,
        public ?string $subject,
        public ?float $timestamp,
        public ?string $spamStatus,
        public ?string $tag,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Coerce::int($data['id'] ?? null),
            token: Coerce::stringOrNull($data['token'] ?? null),
            direction: Coerce::stringOrNull($data['direction'] ?? null),
            messageId: Coerce::stringOrNull($data['message_id'] ?? null),
            to: Coerce::stringOrNull($data['to'] ?? null),
            from: Coerce::stringOrNull($data['from'] ?? null),
            subject: Coerce::stringOrNull($data['subject'] ?? null),
            timestamp: Coerce::floatOrNull($data['timestamp'] ?? null),
            spamStatus: Coerce::stringOrNull($data['spam_status'] ?? null),
            tag: Coerce::stringOrNull($data['tag'] ?? null),
        );
    }
}
