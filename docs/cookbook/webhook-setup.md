---
title: Webhook setup
weight: 32
description: Wire Postal's webhooks to your app — endpoint, signing key, queue and middleware.
---

# Webhook setup

## 1. Register the endpoint in Postal

Postal's admin is web-UI-only (there is no admin API), so this is a manual
step per mail server: **Postal admin → your mail server → Webhooks → Add
webhook**, URL:

```
https://your-app.example/postal/webhook/{server-name}
```

`{server-name}` must match your `postal.servers` key; without it the
delivery is attributed to the default server. Subscribe to all events —
the package handles every type.

## 2. Configure the signing key

Postal signs every webhook with its install-wide RSA key. Fetch it as PEM:

```bash
php artisan postal:webhook-key
```

```dotenv
POSTAL_WEBHOOK_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----\n"
```

Verification is on by default and **fails closed** — without the key every
delivery is rejected with 401. For local development you can switch it off:

```dotenv
POSTAL_WEBHOOK_VERIFY=false
```

## 3. Queueing

The controller acknowledges immediately and processes on your queue.
Optionally isolate the work:

```dotenv
POSTAL_WEBHOOK_QUEUE=webhooks
POSTAL_WEBHOOK_CONNECTION=redis
```

## 4. Middleware and path

```php
'webhooks' => [
    'path' => 'postal/webhook',   // route: POST {path}/{server?}
    'middleware' => ['api'],
],
```

The route is named `postal.webhook`. If your `api` middleware group applies
throttling, make sure Postal's retry bursts fit within it.

## 5. Prove it

```bash
php artisan queue:work &
php artisan postal:tail
```

Send yourself a message; sent/delivered/opened events appear in the tail as
Postal delivers them.
