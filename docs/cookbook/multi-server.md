---
title: Multi-server
weight: 31
description: Configure one named entry per Postal mail server and address them explicitly.
---

# Multi-server

Postal scopes API keys to a *mail server* (usually one per product or
sending domain). Configure one entry per server:

```php
'default' => env('POSTAL_SERVER', 'cbox-id'),

'servers' => [
    'cbox-id' => [
        'url' => env('POSTAL_CBOX_ID_URL', 'https://postal.cbox.dk'),
        'key' => env('POSTAL_CBOX_ID_KEY'),
    ],
    'cbox-billing' => [
        'url' => env('POSTAL_CBOX_BILLING_URL', 'https://postal.cbox.dk'),
        'key' => env('POSTAL_CBOX_BILLING_KEY'),
    ],
],
```

Only list the servers an app actually sends through — the config is the
allow-list; unknown names are rejected everywhere (client, webhooks,
commands).

## Addressing a server

```php
Postal::send(...);                          // default server
Postal::server('cbox-billing')->send(...);  // explicit
```

Mail transport per mailer:

```php
'mailers' => [
    'postal' => ['transport' => 'postal'],                              // default server
    'postal-billing' => ['transport' => 'postal', 'server' => 'cbox-billing'],
],
```

Notifications pick a server by implementing `postalServer($notifiable)` —
see [Notifications](notifications.md).

## Webhooks

Each Postal mail server has its own webhook configuration. Point each one
at its named endpoint so events are attributed correctly:

```
https://your-app.example/postal/webhook/cbox-id
https://your-app.example/postal/webhook/cbox-billing
```

`postal:ping` (no argument) verifies all configured servers at once.
