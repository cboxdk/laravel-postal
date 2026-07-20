---
title: Contracts
weight: 41
description: Factory, Connection, SignatureVerifier and WebhookProcessor — what they do and how to swap them.
---

# Contracts

Everything is contracts-first: depend on the interfaces, and rebind them in
your own service provider to change behaviour.

| Contract | Default binding | Swap it to… |
|---|---|---|
| `Contracts\Factory` | `PostalManager` (also the `postal` container alias and the facade root) | resolve connections differently (e.g. per-tenant credentials) |
| `Contracts\Connection` | `PostalClient` per server | wrap sends with your own instrumentation |
| `Contracts\SignatureVerifier` | `RsaSignatureVerifier` (raw-body RSA, fail closed) | key rotation via JWKS caching, multiple accepted keys |
| `Contracts\WebhookProcessor` | `StoreWebhookProcessor` (dedupe → store → dispatch) | different persistence, extra routing, metrics |
| `Contracts\ServerRegistry` | `ConfigServerRegistry` (reads `postal.servers`) | database-backed / per-tenant server provisioning — see [Dynamic servers](dynamic-servers.md) |
| `Contracts\InboundProcessor` | `StoreInboundProcessor` (dedupe → store → dispatch) | custom inbound routing or persistence |

Example — custom processor that adds metrics but keeps the default
behaviour:

```php
use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Webhooks\StoreWebhookProcessor;

$this->app->extend(WebhookProcessor::class, function (WebhookProcessor $inner) {
    return new class($inner) implements WebhookProcessor
    {
        public function __construct(private readonly WebhookProcessor $inner) {}

        public function process(string $server, $envelope): ?\Cbox\LaravelPostal\Events\PostalWebhookEvent
        {
            $event = $this->inner->process($server, $envelope);

            if ($event !== null) {
                metrics()->increment("postal.webhook.{$event->type()->value}");
            }

            return $event;
        }
    };
});
```

## Configurable behaviour without code

- **Models**: subclass `PostalMessage` / `PostalMessageEvent` and point
  `postal.models.message` / `postal.models.event` at your classes — every
  store read/write in the package resolves through them.
- Webhook and inbound posture (path, middleware, queue, verification,
  store) — [configuration reference](../configuration/reference.md).
- Broadcasting on/off and channel prefix.
- `POSTAL_REDIRECT_TO` for catching all transport mail in dev.
