<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The shared payload of the four delivery-status events (MessageSent,
 * MessageDelayed, MessageDeliveryFailed, MessageHeld).
 */
readonly class MessageStatusPayload
{
    public function __construct(
        public WebhookMessage $message,
        public string $status,
        public ?string $details,
        public ?string $output,
        public bool $sentWithSsl,
        public ?float $timestamp,
        public ?float $time,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            message: WebhookMessage::fromArray(Coerce::map($payload['message'] ?? null)),
            status: Coerce::string($payload['status'] ?? null),
            details: Coerce::stringOrNull($payload['details'] ?? null),
            output: Coerce::stringOrNull($payload['output'] ?? null),
            sentWithSsl: Coerce::bool($payload['sent_with_ssl'] ?? null),
            timestamp: Coerce::floatOrNull($payload['timestamp'] ?? null),
            time: Coerce::floatOrNull($payload['time'] ?? null),
        );
    }
}
