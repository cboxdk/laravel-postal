---
title: Installation
weight: 11
description: Install the package, publish config, run migrations and verify the whole setup with postal:doctor.
---

# Installation

> Prefer letting your AI assistant do this? Copy the prompt from
> [Install with an AI assistant](llm-install.md).

## 1. Require and install

```bash
composer require cboxdk/laravel-postal
php artisan postal:install
php artisan migrate
```

The service provider is auto-discovered. `postal:install` publishes
`config/postal.php` and prints this same checklist; `migrate` creates the
`postal_messages` and `postal_message_events` tables.

The database is fully optional: disable both `postal.webhooks.store` and
`postal.inbound.store` and the package registers **no migrations at all** —
`php artisan migrate` creates nothing, and no code path touches the
database. You keep the typed events; idempotency then falls to your
listeners (dedupe on `uuid()`).

To copy the migrations into your app for editing:

```bash
php artisan postal:install --migrations
```

## 2. Configure servers

One entry per Postal mail server in `config/postal.php` — each mail server
has its own API key (Postal admin → your mail server → Credentials):

```php
'default' => env('POSTAL_SERVER', 'default'),

'servers' => [
    'default' => [
        'url' => env('POSTAL_URL'),
        'key' => env('POSTAL_KEY'),
    ],
],
```

```dotenv
POSTAL_URL=https://postal.example.com
POSTAL_KEY=your-server-api-key
```

See [Multi-server](../cookbook/multi-server.md) for one-server-per-product
setups, [Connection types](../core-concepts/connection-types.md) for
`smtp-api`/`smtp` submission, and
[Dynamic servers](../extension-points/dynamic-servers.md) for
database-driven provisioning.

## 3. Verify

```bash
php artisan postal:ping     # connectivity + key validity per server
php artisan postal:doctor   # everything: config, keys, routes, tables, mailer
```

`postal:doctor` exits non-zero on failures, so it doubles as a deployment
gate. From here:

- [Webhook setup](../cookbook/webhook-setup.md) — delivery/bounce/open/click events.
- [Inbound mail](../cookbook/inbound.md) — receive email into the app.
- [Quickstart](../quickstart.md) — send your first message.
