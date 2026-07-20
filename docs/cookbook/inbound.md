---
title: Inbound mail
weight: 36
description: Receive email into your app via Postal routes delivering to a signed HTTP endpoint.
---

# Inbound mail

Postal routes can deliver received mail straight to your app over HTTP.
The package receives, verifies, deduplicates and turns each message into a
`PostalInboundMessage` event.

## 1. Point a route at your app

Postal admin → your mail server → **Routes** → add a route (e.g.
`support@cboxid.com` or a wildcard) with an **HTTP Endpoint** delivering
to:

```
https://your-app.example/postal/inbound/{server-name}
```

Choose the **BodyAsJSON** encoding (recommended; FormData also works) and
either format:

- **Hash** — parsed fields (subject, bodies, headers, attachments).
- **RawMessage** — the full RFC 2822 message, exposed via `rawMessage()`.

Deliveries are signed with the same install-wide key as webhooks
(`POSTAL_WEBHOOK_PUBLIC_KEY` — fetch with `postal:webhook-key`), and
verification fails closed.

## 2. Listen

```php
use Cbox\LaravelPostal\Events\PostalInboundMessage;

Event::listen(function (PostalInboundMessage $event) {
    $message = $event->payload;              // InboundMessage DTO

    $message->rcptTo;                        // envelope recipient
    $message->mailFrom;                      // envelope sender
    $message->subject;
    $message->plainBody;
    $message->htmlBody;

    foreach ($message->attachments as $attachment) {
        Storage::put($attachment->filename, $attachment->content());
    }

    if ($message->isRaw()) {
        $message->rawMessage();              // full RFC 2822 source
    }
});
```

## 3. Semantics worth knowing

- **Ack fast.** The controller queues `ProcessInboundMessage` and answers
  200 immediately. Configure isolation with `POSTAL_INBOUND_QUEUE` /
  `POSTAL_INBOUND_CONNECTION`.
- **Your response drives Postal's route.** 2xx marks the message
  delivered; 5xx makes Postal retry; other 4xx (including a signature
  rejection) hard-fails and may bounce — so a misconfigured public key
  stops inbound flow visibly rather than silently.
- **Idempotent.** Redeliveries deduplicate on the Postal message id before
  any side effect, atomically — a failure mid-processing rolls the dedupe
  marker back so the retry is clean. Every `PostalInboundMessage` exposes
  the same key via `uuid()` for listener-side dedupe.
- **Mind your queue driver's payload limit.** The queued job carries the
  full delivery body (attachments arrive base64-encoded). SQS caps
  payloads at 256 KB — for attachment-heavy mail use Redis/database
  queues, or configure the Postal route without attachments and fetch
  bodies via `Postal::message()` instead. Attachment bytes are decoded
  lazily (`$attachment->content()`), so metadata-only listeners never pay
  for them.
- **Stored.** With the store enabled, each inbound message gets a
  `postal_messages` row (`direction = incoming`, status `Received`) and an
  `InboundMessage` entry in the event log — `postal:tail` shows inbound
  traffic live. Attachment data and raw source are deliberately kept out
  of the log; persist those in your listener if you need them.
