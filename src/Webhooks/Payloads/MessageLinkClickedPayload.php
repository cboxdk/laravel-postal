<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The MessageLinkClicked payload.
 */
readonly class MessageLinkClickedPayload
{
    public function __construct(
        public WebhookMessage $message,
        public ?string $url,
        public ?string $token,
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
            url: Coerce::stringOrNull($payload['url'] ?? null),
            token: Coerce::stringOrNull($payload['token'] ?? null),
            ipAddress: Coerce::stringOrNull($payload['ip_address'] ?? null),
            userAgent: Coerce::stringOrNull($payload['user_agent'] ?? null),
        );
    }
}
