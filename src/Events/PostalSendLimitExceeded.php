<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\SendLimitPayload;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * A server's send volume exceeded its configured limit.
 */
class PostalSendLimitExceeded extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly SendLimitPayload $payload,
        ?string $uuid = null,
        ?float $timestamp = null,
    ) {
        parent::__construct($server, $uuid, $timestamp);
    }

    public function type(): WebhookEventType
    {
        return WebhookEventType::SendLimitExceeded;
    }
}
