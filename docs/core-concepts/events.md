---
title: Events
weight: 22
description: The full catalog of typed webhook events, their payload DTOs, and how to listen.
---

# Events

One event class exists per Postal webhook event type; all implement
`Cbox\LaravelPostal\Events\PostalWebhookEvent` and expose `server()`,
`type()`, `uuid()` (Postal's webhook request uuid — the idempotency key) and
`occurredAt()`.

| Event class | Postal event | Payload DTO |
|---|---|---|
| `PostalMessageSent` | `MessageSent` | `MessageStatusPayload` |
| `PostalMessageDelayed` | `MessageDelayed` | `MessageStatusPayload` |
| `PostalMessageDeliveryFailed` | `MessageDeliveryFailed` | `MessageStatusPayload` |
| `PostalMessageHeld` | `MessageHeld` | `MessageStatusPayload` |
| `PostalMessageBounced` | `MessageBounced` | `MessageBouncedPayload` |
| `PostalMessageLinkClicked` | `MessageLinkClicked` | `MessageLinkClickedPayload` |
| `PostalMessageLoaded` (= opened) | `MessageLoaded` | `MessageLoadedPayload` |
| `PostalDomainDnsError` | `DomainDNSError` | `DomainDnsErrorPayload` |
| `PostalSendLimitApproaching` | `SendLimitApproaching` | `SendLimitPayload` |
| `PostalSendLimitExceeded` | `SendLimitExceeded` | `SendLimitPayload` |
| `PostalInboundMessage` | — (route → HTTP endpoint, not a webhook) | `InboundMessage` |

Payload shapes were verified against Postal 3.3.7's source, not guessed
from docs:

- **`MessageStatusPayload`** — `message` (`WebhookMessage`), `status`
  (`Sent`/`SoftFail`/`HardFail`/`Held` as Postal reports it), `details`,
  `output` (SMTP transcript excerpt), `sentWithSsl`, `timestamp`, `time`.
- **`MessageBouncedPayload`** — `originalMessage` and `bounce`, both
  `WebhookMessage` (the bounce is its own inbound message).
- **`MessageLinkClickedPayload`** — `message`, `url`, `token`, `ipAddress`,
  `userAgent`.
- **`MessageLoadedPayload`** — `message`, `ipAddress`, `userAgent`.
- **`DomainDnsErrorPayload`** — `server` (`ServerInfo`), `domain`, `uuid`,
  `dnsCheckedAt`, and per-record `spf/dkim/mx/returnPath` status + error.
- **`SendLimitPayload`** — `server` (`ServerInfo`), `volume`, `limit`.

`WebhookMessage` carries `id` (Postal's internal id), `token`, `direction`,
`messageId` (RFC id), `to`, `from`, `subject`, `timestamp`, `spamStatus`,
`tag`.

## Listening

```php
use Cbox\LaravelPostal\Events\PostalMessageDeliveryFailed;

Event::listen(function (PostalMessageDeliveryFailed $event) {
    // typed all the way down
    $event->payload->message->to;
    $event->payload->output;
});
```

Events are dispatched by the queued webhook job, so listeners run on your
queue workers. Unknown event types from future Postal versions are ignored
(deny-by-default) — nothing is stored and no event fires.
