<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

/**
 * Every webhook event type Postal 3.x dispatches, verbatim.
 */
enum WebhookEventType: string
{
    case MessageSent = 'MessageSent';
    case MessageDelayed = 'MessageDelayed';
    case MessageDeliveryFailed = 'MessageDeliveryFailed';
    case MessageHeld = 'MessageHeld';
    case MessageBounced = 'MessageBounced';
    case MessageLinkClicked = 'MessageLinkClicked';
    case MessageLoaded = 'MessageLoaded';
    case DomainDNSError = 'DomainDNSError';
    case SendLimitApproaching = 'SendLimitApproaching';
    case SendLimitExceeded = 'SendLimitExceeded';

    /**
     * Not a webhook: an inbound message delivered by a Postal route to an
     * HTTP endpoint. It shares the event pipeline (store, tail, broadcast),
     * so it lives in this enum alongside the webhook types.
     */
    case InboundMessage = 'InboundMessage';

    /**
     * The four delivery-status events share one payload shape.
     */
    public function isDeliveryStatus(): bool
    {
        return match ($this) {
            self::MessageSent, self::MessageDelayed, self::MessageDeliveryFailed, self::MessageHeld => true,
            default => false,
        };
    }
}
