<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\MessageLinkClickedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\WebhookMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * A tracked link in a message was clicked.
 */
class PostalMessageLinkClicked extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly MessageLinkClickedPayload $payload,
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
        return WebhookEventType::MessageLinkClicked;
    }
}
