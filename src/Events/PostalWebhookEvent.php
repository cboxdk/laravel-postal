<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * Marker interface for every typed Postal webhook event. Listen to a concrete
 * event class for one type, or to this interface's implementors for all.
 */
interface PostalWebhookEvent
{
    /**
     * The configured server name the webhook arrived for.
     */
    public function server(): string;

    public function type(): WebhookEventType;

    /**
     * Postal's webhook request uuid — the idempotency key for redeliveries.
     */
    public function uuid(): ?string;

    /**
     * Unix timestamp (with fractions) of when Postal created the webhook.
     */
    public function occurredAt(): ?float;
}
