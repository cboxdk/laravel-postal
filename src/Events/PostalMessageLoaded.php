<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\MessageLoadedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\WebhookMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * A message was opened (the tracking pixel was loaded).
 */
class PostalMessageLoaded extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly MessageLoadedPayload $payload,
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
        return WebhookEventType::MessageLoaded;
    }
}
