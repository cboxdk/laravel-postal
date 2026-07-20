<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The MessageBounced payload: the original outbound message plus the inbound
 * bounce message Postal matched to it.
 */
readonly class MessageBouncedPayload
{
    public function __construct(
        public WebhookMessage $originalMessage,
        public WebhookMessage $bounce,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            originalMessage: WebhookMessage::fromArray(Coerce::map($payload['original_message'] ?? null)),
            bounce: WebhookMessage::fromArray(Coerce::map($payload['bounce'] ?? null)),
        );
    }
}
