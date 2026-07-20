<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `details` expansion of a message lookup — envelope and routing metadata.
 */
readonly class MessageMeta
{
    public function __construct(
        public ?string $rcptTo,
        public ?string $mailFrom,
        public ?string $subject,
        public ?string $messageId,
        public ?float $timestamp,
        public ?string $direction,
        public ?int $size,
        public bool $bounce,
        public ?int $bounceForId,
        public ?string $tag,
        public bool $receivedWithSsl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rcptTo: Coerce::stringOrNull($data['rcpt_to'] ?? null),
            mailFrom: Coerce::stringOrNull($data['mail_from'] ?? null),
            subject: Coerce::stringOrNull($data['subject'] ?? null),
            messageId: Coerce::stringOrNull($data['message_id'] ?? null),
            timestamp: Coerce::floatOrNull($data['timestamp'] ?? null),
            direction: Coerce::stringOrNull($data['direction'] ?? null),
            size: Coerce::intOrNull($data['size'] ?? null),
            bounce: Coerce::bool($data['bounce'] ?? null),
            bounceForId: Coerce::intOrNull($data['bounce_for_id'] ?? null),
            tag: Coerce::stringOrNull($data['tag'] ?? null),
            receivedWithSsl: Coerce::bool($data['received_with_ssl'] ?? null),
        );
    }
}
