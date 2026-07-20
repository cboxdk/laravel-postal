<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\MessageBouncedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\WebhookMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * A bounce message was received for an outbound message.
 */
class PostalMessageBounced extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly MessageBouncedPayload $payload,
        ?string $uuid = null,
        ?float $timestamp = null,
    ) {
        parent::__construct($server, $uuid, $timestamp);
    }

    public function relatedMessage(): ?WebhookMessage
    {
        return $this->payload->originalMessage;
    }

    public function type(): WebhookEventType
    {
        return WebhookEventType::MessageBounced;
    }
}
