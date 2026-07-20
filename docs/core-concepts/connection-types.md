---
title: Connection types
weight: 24
description: The three ways a connection submits mail — api, smtp-api and smtp — and what each supports.
---

# Connection types

Every server entry declares how mail is submitted via its `type`:

| Type | Submits via | Credential (Postal) | Send result | Lookups / API ping |
|---|---|---|---|---|
| `api` (default) | `/api/v1/send/message` (structured JSON) | API key | Postal message ids per recipient | yes |
| `smtp-api` | `/api/v1/send/raw` (RFC 2822 over HTTP) | API key | Postal message ids per recipient | yes |
| `smtp` | classic SMTP submission | SMTP or SMTP-IP credential | MIME Message-ID only | only when `url` + `key` are also set |

## api

```php
'cbox-id' => ['url' => 'https://postal.cbox.dk', 'key' => env('POSTAL_CBOX_ID_KEY')],
```

`send(SendMessage)` posts the structured payload; `sendRaw()` uses the raw
endpoint. Full lookups (`message()`, `deliveries()`) and API ping.

## smtp-api

```php
'cbox-id' => ['url' => ..., 'key' => ..., 'type' => 'smtp-api'],
```

Same API credential, but `send(SendMessage)` renders the message to MIME
and submits it through `/send/raw` — SMTP semantics (your exact MIME on the
wire) with API ergonomics and per-recipient Postal ids. The tag travels as
an `X-Postal-Tag` header.

## smtp

```php
'cbox-legacy' => [
    'type' => 'smtp',
    'smtp' => [
        'host' => 'postal.cbox.dk',
        'port' => 25,
        'username' => env('POSTAL_SMTP_USER'),   // omit both for SMTP-IP
        'password' => env('POSTAL_SMTP_PASS'),
        'tls' => false,                          // implicit TLS; STARTTLS is automatic
    ],
    // optional: 'url' + 'key' enable message()/deliveries() alongside
],
```

Submission runs over Symfony's SMTP transport. SMTP acceptance returns no
Postal ids, so the `SendResult` carries only the MIME Message-ID —
correlation happens through the webhook spine. `ping()` performs a real
SMTP handshake (connect/EHLO/quit). Calling `message()`/`deliveries()`
without API credentials raises `UnsupportedOperationException`.

All three types drive the same `Contracts\Connection` interface, so
application code does not change when a server's type does.
