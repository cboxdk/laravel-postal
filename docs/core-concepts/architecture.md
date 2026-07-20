---
title: Architecture
weight: 21
description: The send path, the observe path, and the contracts that join them.
---

# Architecture

## Send path

```
Postal facade ─▶ PostalManager (Contracts\Factory)
                   └─▶ PostalClient per server (Contracts\Connection)
                         └─▶ PendingRequest  — auth header, retries,
                               envelope unwrapping, typed exceptions
```

- `PostalManager` caches one `PostalClient` per configured server. Calls on
  the manager (and thus the facade) proxy to the default server.
- `PendingRequest` is where Postal's envelope quirk is handled: the legacy
  API answers **HTTP 200 for application errors**, so the `status` attribute
  of the `{status, time, flags, data}` envelope is authoritative. Error
  codes map to typed exceptions (`AuthenticationException`,
  `ValidationException`, `MessageNotFoundException`, `RateLimitException`,
  `ServerException`); `parameter-error` envelopes become
  `ValidationException`.
- Retries apply to transport failures, HTTP 429 and 5xx only. Error
  envelopes are never retried — they are deterministic rejections.
- The mail transport (`PostalTransport`) rides the same client via
  `/api/v1/send/raw` and stamps the returned Postal message id onto the sent
  message and an `X-Postal-Message-Id` header.

## Observe path

```
POST /postal/webhook/{server?}
  └─▶ WebhookController — raw body capture, signature verify (fail closed)
        └─▶ ProcessWebhook (queued) — ack fast, process async
              └─▶ Contracts\WebhookProcessor (StoreWebhookProcessor)
                    ├─ dedupe on Postal's webhook uuid
                    ├─ upsert postal_messages status row
                    └─ dispatch one typed event
```

Postal expects a 2xx within ~5 seconds and retries with backoff (5 attempts
over ~36 minutes on 3.x). The controller therefore does nothing but
validate, enqueue and acknowledge.

The inbound path (`POST /postal/inbound/{server?}`) mirrors this shape —
same signature verification, its own queued job and
`Contracts\InboundProcessor` — see [Inbound mail](../cookbook/inbound.md).

## Boundaries

- DTO hydration happens only at the serialization boundary via `Coerce` —
  `mixed` never leaks into the domain; everything downstream is `readonly`
  value objects and enums.
- The pure logic (DTOs, envelope, signature verification) lives in
  framework-light classes that unit-test without booting Laravel.
- Broadcasting is opt-in and rides Laravel's own abstraction — see
  [Broadcasting](../cookbook/broadcasting.md).
