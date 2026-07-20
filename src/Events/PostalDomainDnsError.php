<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Webhooks\Payloads\DomainDnsErrorPayload;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * Postal's automatic DNS checks found a problem with a sending domain.
 */
class PostalDomainDnsError extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly DomainDnsErrorPayload $payload,
        ?string $uuid = null,
        ?float $timestamp = null,
    ) {
        parent::__construct($server, $uuid, $timestamp);
    }

    public function type(): WebhookEventType
    {
        return WebhookEventType::DomainDNSError;
    }
}
