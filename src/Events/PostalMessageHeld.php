<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\MessageStatusPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\WebhookMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * Postal reported the "MessageHeld" delivery status for a message.
 */
class PostalMessageHeld extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly MessageStatusPayload $payload,
        ?string $uuid = null,
        ?float $timestamp = null,
    ) {
        parent::__construct($server, $uuid, $timestamp);
    }

    public function relatedMessage(): ?WebhookMessage
    {
        return $this->payload->message;
    }

    public function type(): WebhookEventType
    {
        return WebhookEventType::MessageHeld;
    }
}
