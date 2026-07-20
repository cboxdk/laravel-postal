---
title: Quickstart
weight: 2
description: Install, configure a server, send a message and receive your first webhook.
---

# Quickstart

## 1. Install

```bash
composer require cboxdk/laravel-postal
php artisan postal:install
php artisan migrate
```

> Tip: there is a [copy-paste prompt](getting-started/llm-install.md) that
> lets your AI assistant do this entire page for you.

## 2. Configure a server

Every Postal *mail server* has its own API key (Postal admin → Credentials).

```dotenv
POSTAL_URL=https://postal.example.com
POSTAL_KEY=your-server-api-key
```

Prove the URL and key work:

```bash
php artisan postal:ping
```

## 3. Send

Either flip the mail transport:

```php
// config/mail.php
'mailers' => [
    'postal' => ['transport' => 'postal'],
],
```

```dotenv
MAIL_MAILER=postal
```

…or use the typed API:

```php
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Facades\Postal;

Postal::send(
    SendMessage::create()
        ->to('alice@example.com')
        ->from('Cbox <no-reply@example.com>')
        ->subject('Hello')
        ->html('<p>Hello!</p>'),
);
```

## 4. Observe

In the Postal web UI, add a webhook pointing at your app:

```
https://your-app.example/postal/webhook
```

Fetch the signing key and put it in `.env`:

```bash
php artisan postal:webhook-key
```

```dotenv
POSTAL_WEBHOOK_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----\n"
```

Listen for events:

```php
use Cbox\LaravelPostal\Events\PostalMessageDeliveryFailed;

Event::listen(function (PostalMessageDeliveryFailed $event) {
    logger()->warning('Delivery failed', [
        'server' => $event->server(),
        'to' => $event->payload->message->to,
        'output' => $event->payload->output,
    ]);
});
```

And watch the spine live while you test:

```bash
php artisan postal:tail
```

## 5. Check your work

```bash
php artisan postal:doctor
```

One command that verifies servers, keys, routes, tables and connectivity —
and exits non-zero on problems, so it drops straight into CI.
