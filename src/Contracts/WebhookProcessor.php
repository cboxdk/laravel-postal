<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

use Cbox\LaravelPostal\Events\PostalWebhookEvent;
use Cbox\LaravelPostal\Webhooks\WebhookEnvelope;

/**
 * Processes one verified webhook delivery: dedupe, persist, dispatch.
 */
interface WebhookProcessor
{
    /**
     * Returns the dispatched event, or null when the delivery was a
     * duplicate or carried an unknown event type.
     */
    public function process(string $server, WebhookEnvelope $envelope): ?PostalWebhookEvent;
}
