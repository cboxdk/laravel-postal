---
title: Reference
weight: 61
description: Every config/postal.php key, its environment variable and default value.
---

# Configuration reference

Publish with `php artisan vendor:publish --tag=postal-config`.

## Servers

| Key | Env | Default | Notes |
|---|---|---|---|
| `default` | `POSTAL_SERVER` | `default` | Server used when none is named |
| `servers.{name}.url` | `POSTAL_URL` (default entry) | — | Base URL of the Postal install (required for api types; optional for smtp) |
| `servers.{name}.key` | `POSTAL_KEY` (default entry) | — | That mail server's `X-Server-API-Key` |
| `servers.{name}.type` | `POSTAL_TYPE` (default entry) | `api` | `api`, `smtp-api` or `smtp` — see [Connection types](../core-concepts/connection-types.md) |
| `servers.{name}.smtp.host` | — | — | Required for type `smtp` |
| `servers.{name}.smtp.port` | — | `25` | |
| `servers.{name}.smtp.username` | — | `null` | Omit both for SMTP-IP credentials |
| `servers.{name}.smtp.password` | — | `null` | |
| `servers.{name}.smtp.tls` | — | `false` | Implicit TLS; STARTTLS is negotiated automatically |
| `servers.{name}.smtp.timeout` | — | `30` | Socket timeout in seconds |

Servers can also come from a custom registry or ad hoc connections instead
of config — see [Dynamic servers](../extension-points/dynamic-servers.md).

## HTTP client

| Key | Env | Default | Notes |
|---|---|---|---|
| `http.timeout` | `POSTAL_TIMEOUT` | `15` | Seconds per request |
| `http.retry.times` | `POSTAL_RETRY_TIMES` | `3` | Attempts on transport errors / 429 / 5xx only |
| `http.retry.sleep_ms` | `POSTAL_RETRY_SLEEP_MS` | `200` | Delay between attempts |

## Webhooks

| Key | Env | Default | Notes |
|---|---|---|---|
| `webhooks.enabled` | `POSTAL_WEBHOOKS_ENABLED` | `true` | Registers `POST {path}/{server?}` |
| `webhooks.path` | — | `postal/webhook` | Route name `postal.webhook` |
| `webhooks.middleware` | — | `['api']` | Middleware group for the route |
| `webhooks.verify_signature` | `POSTAL_WEBHOOK_VERIFY` | `true` | Fails closed without a key |
| `webhooks.public_key` | `POSTAL_WEBHOOK_PUBLIC_KEY` | `null` | PEM; fetch via `postal:webhook-key` |
| `webhooks.queue` | `POSTAL_WEBHOOK_QUEUE` | `null` | Queue for `ProcessWebhook` |
| `webhooks.connection` | `POSTAL_WEBHOOK_CONNECTION` | `null` | Queue connection |
| `webhooks.store` | `POSTAL_WEBHOOK_STORE` | `true` | Status rows + dedupe log; with both store flags off, no migrations are registered |

## Inbound

| Key | Env | Default | Notes |
|---|---|---|---|
| `inbound.enabled` | `POSTAL_INBOUND_ENABLED` | `true` | Registers `POST {path}/{server?}` |
| `inbound.path` | — | `postal/inbound` | Route name `postal.inbound` |
| `inbound.middleware` | — | `['api']` | |
| `inbound.verify_signature` | `POSTAL_INBOUND_VERIFY` | `true` | Uses `webhooks.public_key`; fails closed |
| `inbound.queue` | `POSTAL_INBOUND_QUEUE` | `null` | Queue for `ProcessInboundMessage` |
| `inbound.connection` | `POSTAL_INBOUND_CONNECTION` | `null` | |
| `inbound.store` | `POSTAL_INBOUND_STORE` | `true` | `direction = incoming` rows + event log |

## Broadcasting

| Key | Env | Default | Notes |
|---|---|---|---|
| `broadcast.enabled` | `POSTAL_BROADCAST` | `false` | Opt-in |
| `broadcast.channel` | — | `postal` | Private-channel prefix |

## Models

| Key | Default | Notes |
|---|---|---|
| `models.message` | `PostalMessage::class` | Point at a subclass to extend the status row model |
| `models.event` | `PostalMessageEvent::class` | Point at a subclass to extend the event log model |

## Misc

| Key | Env | Default | Notes |
|---|---|---|---|
| `redirect_to` | `POSTAL_REDIRECT_TO` | `null` | Transport-level catch-all recipient (dev) |
