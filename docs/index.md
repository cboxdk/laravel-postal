---
title: Cbox Postal
weight: 1
description: Postal mail server integration for Laravel — typed multi-server sending plus a verified, event-driven webhook observability spine.
---

# Cbox Postal

Postal mail server integration for Laravel. Where most mail-driver packages
stop at "send and forget", this package treats a
[Postal](https://docs.postalserver.io) install as a **live, typed delivery
bus**: it owns both **sending** (a typed API client and a `postal` mail
transport) and **observing** (verified webhooks that become typed Laravel
events and an idempotent per-message status store).

## Mental model

```
your app ──send──▶  PostalClient ──HTTP──▶  Postal server(s)
your app ◀─events── WebhookProcessor ◀──signed webhooks──┘
```

- **One `PostalClient` per configured server.** Every Postal mail server has
  its own API key, so multi-product deployments configure one named entry
  per server and address them with `Postal::server('name')`.
- **Signals, not sockets.** The webhook spine produces two durable products:
  typed events (one class per Postal event type) and an idempotent
  `postal_messages` status row per message. Real-time delivery to browsers
  is the consuming app's choice — broadcasting is opt-in sugar over
  Laravel's own abstraction, with no broadcaster dependency.
- **Deny-by-default.** Webhook signature verification fails closed, unknown
  event types are ignored, and unknown server names 404.

## Scope: why there is no admin/provisioning client

Postal v3 exposes exactly four HTTP API endpoints — `send/message`,
`send/raw`, `messages/message`, `messages/deliveries` — plus the JWKS key
endpoint. **This package covers all of them.** Everything administrative
(organizations, mail servers, domains, credentials, webhooks, routes) is
served only as Postal's session-authenticated web UI; no admin API exists
in any released version (verified against the 3.3.7 routes and Postal's
main branch). Driving that UI with a scraper would be brittle and
version-locked, so this package deliberately does not attempt it — the few
one-time UI steps are called out in [Webhook setup](cookbook/webhook-setup.md)
and [Inbound mail](cookbook/inbound.md). If Postal ships its long-planned
admin API, a typed client for it belongs here and will be added.

## Sections

- [Quickstart](quickstart.md) — zero to sending and observing in one read.
- [Getting started](getting-started/_index.md) — installation and testing
  support.
- [Core concepts](core-concepts/_index.md) — architecture, the event
  catalog, the status store.
- [Cookbook](cookbook/_index.md) — multi-server setups, webhooks,
  notifications, broadcasting, health checks.
- [Extension points](extension-points/_index.md) — the contracts you can
  rebind.
- [Security](security/_index.md) — webhook signatures and the threat model.
- [Configuration](configuration/_index.md) — every config key.
