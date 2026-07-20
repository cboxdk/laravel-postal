---
title: Status store
weight: 23
description: The postal_messages status row, the deduplicated event log, and how idempotency works.
---

# Status store

With `postal.webhooks.store` enabled (the default), the webhook processor
maintains two tables. The store is entirely optional — with both store
flags disabled the package registers no migrations and never touches the
database.

## `postal_messages` — one row per message

Keyed unique on `(server, postal_message_id)`. Holds the latest known
delivery `status` (+ `status_details`), envelope fields (`to`, `from`,
`subject`, `tag`, `message_id`, `token`), `opens` / `clicks` counters, the
`last_event` + `last_event_at`, and an optional `notifiable` morph set by
the [notification channel](../cookbook/notifications.md).

Query via the `PostalMessage` model:

```php
use Cbox\LaravelPostal\Models\PostalMessage;

PostalMessage::query()->forServer('cbox-billing')->bounced()->get();
PostalMessage::query()->delivered()->where('tag', 'onboarding')->count();
```

Scopes: `forServer()`, `delivered()`, `bounced()`, `failed()`, `held()`.

## `postal_message_events` — the deduplicated log

Every processed delivery is appended with its payload and a unique
`dedupe_key`. `php artisan postal:tail` follows this table.

## Idempotency

Postal retries webhook deliveries with backoff until it sees a 2xx, so the
same delivery can arrive more than once. Every webhook body carries a
`uuid` per webhook request; the processor inserts
`{server}:{uuid}` as a unique `dedupe_key` — a duplicate insert means
redelivery, and the processor stops: no counter is incremented twice and
no event is dispatched twice. Bodies without a uuid fall back to a content
hash; inbound messages dedupe on their Postal message id.

Processing is **transactional**: the dedupe insert, the status-row update
and the event dispatch commit together. If anything fails mid-way, the
dedupe marker rolls back and the redelivery retries cleanly — a crash can
never permanently swallow a delivery. Status rows are created with
`insertOrIgnore` and mutated under a row lock, so concurrent workers on
the same message cannot double-create rows or lose open/click counts.

With the store disabled there is no dedupe state, so redeliveries dispatch
the event again — listeners must then tolerate duplicates themselves
(`uuid()` on every event exists for exactly that).
