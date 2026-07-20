---
title: Broadcasting
weight: 34
description: Opt-in real-time fan-out of webhook events over your app's own broadcaster.
---

# Broadcasting

Off by default, and the package depends on **no** broadcaster. When
enabled, every webhook event also broadcasts over whatever
`broadcasting.default` your app configures (Reverb, Pusher, Ably, Redis,
log, …) — the events implement `ShouldBroadcast` gated by
`broadcastWhen()`, so a disabled install does zero broadcasting work.

```dotenv
POSTAL_BROADCAST=true
```

Events broadcast on a private channel per server, named after the Postal
event type:

```
channel:  private-postal.server.{server-name}
event:    MessageSent, MessageBounced, …
```

The broadcast payload is deliberately **identifiers only** — server, event
type, uuid, timestamp, and the related message's id/token/addresses/subject.
Message bodies, SMTP transcripts, attachments and raw MIME never enter the
broadcast payload (they would leak content to the websocket transport and
blow payload limits). A client that needs more reacts to the signal and
fetches details through your own API.

Authorize the channel in your app:

```php
// routes/channels.php
Broadcast::channel('postal.server.{server}', function ($user, string $server) {
    return $user->can('view-mail-status');
});
```

And subscribe (Echo):

```js
Echo.private('postal.server.cbox-billing')
    .listen('.MessageBounced', (event) => {
        console.log(event.message.to);
    });
```

The channel prefix (`postal`) is configurable via
`postal.broadcast.channel`.

If you need per-message channels, granular payload shaping or public
channels, listen to the typed events and broadcast your own events — the
package deliberately keeps its broadcast surface minimal.
