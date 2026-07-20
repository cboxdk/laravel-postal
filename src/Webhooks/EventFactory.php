<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Events\PostalDomainDnsError;
use Cbox\LaravelPostal\Events\PostalEvent;
use Cbox\LaravelPostal\Events\PostalMessageBounced;
use Cbox\LaravelPostal\Events\PostalMessageDelayed;
use Cbox\LaravelPostal\Events\PostalMessageDeliveryFailed;
use Cbox\LaravelPostal\Events\PostalMessageHeld;
use Cbox\LaravelPostal\Events\PostalMessageLinkClicked;
use Cbox\LaravelPostal\Events\PostalMessageLoaded;
use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Events\PostalSendLimitApproaching;
use Cbox\LaravelPostal\Events\PostalSendLimitExceeded;
use Cbox\LaravelPostal\Webhooks\Payloads\DomainDnsErrorPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageBouncedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageLinkClickedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageLoadedPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageStatusPayload;
use Cbox\LaravelPostal\Webhooks\Payloads\SendLimitPayload;

/**
 * Turns a parsed webhook envelope into the matching typed event. Unknown
 * event types map to null — deny-by-default, the caller decides how to log.
 */
class EventFactory
{
    public function make(string $server, WebhookEnvelope $envelope): ?PostalEvent
    {
        $type = $envelope->type();

        if ($type === null) {
            return null;
        }

        $payload = $envelope->payload;
        $uuid = $envelope->uuid;
        $timestamp = $envelope->timestamp;

        return match ($type) {
            WebhookEventType::MessageSent => new PostalMessageSent($server, MessageStatusPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageDelayed => new PostalMessageDelayed($server, MessageStatusPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageDeliveryFailed => new PostalMessageDeliveryFailed($server, MessageStatusPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageHeld => new PostalMessageHeld($server, MessageStatusPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageBounced => new PostalMessageBounced($server, MessageBouncedPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageLinkClicked => new PostalMessageLinkClicked($server, MessageLinkClickedPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::MessageLoaded => new PostalMessageLoaded($server, MessageLoadedPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::DomainDNSError => new PostalDomainDnsError($server, DomainDnsErrorPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::SendLimitApproaching => new PostalSendLimitApproaching($server, SendLimitPayload::fromArray($payload), $uuid, $timestamp),
            WebhookEventType::SendLimitExceeded => new PostalSendLimitExceeded($server, SendLimitPayload::fromArray($payload), $uuid, $timestamp),
            // Inbound messages arrive via their own endpoint, never as a
            // webhook — a webhook claiming this type is not honoured.
            WebhookEventType::InboundMessage => null,
        };
    }
}
