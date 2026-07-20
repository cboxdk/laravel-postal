<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The MessageLoaded (open-tracking) payload.
 */
readonly class MessageLoadedPayload
{
    public function __construct(
        public WebhookMessage $message,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            message: WebhookMessage::fromArray(Coerce::map($payload['message'] ?? null)),
            ipAddress: Coerce::stringOrNull($payload['ip_address'] ?? null),
            userAgent: Coerce::stringOrNull($payload['user_agent'] ?? null),
        );
    }
}
