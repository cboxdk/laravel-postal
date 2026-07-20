---
title: Notifications
weight: 33
description: Send notifications through the postal channel and link sent messages to their models.
---

# Notifications

The `postal` channel sends through the typed API (not the mail transport)
and can link every sent message to the notified model, so later webhook
events land on a row that already knows its owner.

```php
use Cbox\LaravelPostal\Dto\SendMessage;
use Illuminate\Notifications\Notification;

class InvoiceReady extends Notification
{
    public function via(object $notifiable): array
    {
        return ['postal'];
    }

    public function toPostal(object $notifiable): SendMessage
    {
        return SendMessage::create()
            ->from('billing@example.com')
            ->subject('Your invoice is ready')
            ->tag('invoices')
            ->html('<p>…</p>');
    }

    // Optional — defaults to the default server.
    public function postalServer(object $notifiable): string
    {
        return 'cbox-billing';
    }
}
```

## Recipient routing

When `toPostal()` sets no recipient, the channel falls back to the
notifiable's routing: `routeNotificationForPostal()` first, then the mail
route (including `['address' => 'name']` maps).

## Model linking

With the store enabled, one `postal_messages` row per recipient is created
at send time with the `notifiable` morph set:

```php
use Cbox\LaravelPostal\Models\PostalMessage;

$user->morphMany(PostalMessage::class, 'notifiable');

PostalMessage::query()
    ->whereMorphedTo('notifiable', $user)
    ->bounced()
    ->exists(); // did anything we sent this user bounce?
```

Webhook events then update those same rows (status, opens, clicks) because
they share the `(server, postal_message_id)` key.
