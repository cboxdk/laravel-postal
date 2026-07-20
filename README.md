# Cbox Postal

**Postal mail server integration for Laravel** — a typed, multi-server send
transport plus a verified, event-driven webhook observability spine
(delivery, bounce, open, click, DNS).

[Postal](https://docs.postalserver.io) is a self-hosted mail server with an
HTTP send API and signed webhooks. This package treats it as a live, typed
delivery bus rather than a fire-and-forget mail driver:

- **Send** through a fully-typed client (`Postal::send(...)`) or transparently
  via `MAIL_MAILER=postal`, with retries, typed exceptions and correct
  handling of Postal's HTTP-200-with-error envelopes. Three connection
  types per server: `api` (structured JSON), `smtp-api` (raw RFC 2822 over
  the HTTP API) and `smtp` (classic SMTP submission).
- **Observe** through verified webhooks: RSA signatures are checked over the
  raw request body, deliveries are deduplicated on Postal's webhook uuid, an
  idempotent per-message status row is maintained, and one typed Laravel
  event fires per Postal event type.
- **Receive** inbound email: Postal routes deliver to a signed HTTP
  endpoint that becomes a typed `PostalInboundMessage` event — bodies,
  headers, attachments and raw source included.
- **Multiple servers** are first-class: one config entry per Postal mail
  server, addressed by name — or provisioned dynamically from a database
  via the `ServerRegistry` contract / `Postal::connect()`.

## Installation

```bash
composer require cboxdk/laravel-postal
php artisan postal:install
php artisan migrate
```

Configure at least one server in `.env`:

```dotenv
POSTAL_URL=https://postal.example.com
POSTAL_KEY=your-server-api-key
```

Then verify everything — servers, keys, routes, tables, connectivity:

```bash
php artisan postal:doctor
```

### Install with your AI assistant

Working with Claude Code, Cursor or similar? Paste the setup prompt from
[docs/getting-started/llm-install.md](docs/getting-started/llm-install.md)
into your assistant — it performs the whole installation, pauses for the
two Postal-UI steps, and finishes with a green `postal:doctor` run.

## Sending

As a mail transport — add a mailer and point `MAIL_MAILER` at it:

```php
// config/mail.php
'mailers' => [
    'postal' => ['transport' => 'postal'], // optional: 'server' => 'cbox-billing'
],
```

Or through the typed API:

```php
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Facades\Postal;

$result = Postal::send(
    SendMessage::create()
        ->to('alice@example.com')
        ->from('Cbox <no-reply@cboxid.com>')
        ->subject('Welcome')
        ->tag('onboarding')
        ->html('<p>Hello!</p>'),
);

$result->messageId;                          // RFC message id Postal assigned
$result->recipient('alice@example.com')->id; // Postal's per-recipient id
```

Or per server: `Postal::server('cbox-billing')->send(...)`.

## Observing

Point each Postal mail server's webhook at your app (Postal admin →
Webhooks; the URL suffix names the server the delivery belongs to):

```
https://your-app.example/postal/webhook/cbox-billing
```

Fetch the signing key and set it in `.env`:

```bash
php artisan postal:webhook-key
# → POSTAL_WEBHOOK_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n..."
```

Then subscribe to the typed events:

```php
use Cbox\LaravelPostal\Events\PostalMessageBounced;

Event::listen(function (PostalMessageBounced $event) {
    $event->server();                        // 'cbox-billing'
    $event->payload->originalMessage->to;    // who bounced
});
```

One event class exists per Postal event type: `PostalMessageSent`,
`PostalMessageDelayed`, `PostalMessageDeliveryFailed`, `PostalMessageHeld`,
`PostalMessageBounced`, `PostalMessageLinkClicked`, `PostalMessageLoaded`
(= opened), `PostalDomainDnsError`, `PostalSendLimitApproaching`,
`PostalSendLimitExceeded`.

The store keeps one idempotent row per message in `postal_messages`
(latest status, opens/clicks counters, owning model) plus a deduplicated
event log in `postal_message_events`. Watch it live:

```bash
php artisan postal:tail
```

## Receiving

Point a Postal route's HTTP endpoint at
`https://your-app.example/postal/inbound/{server}` and listen:

```php
use Cbox\LaravelPostal\Events\PostalInboundMessage;

Event::listen(function (PostalInboundMessage $event) {
    $event->payload->subject;
    $event->payload->plainBody;
    $event->payload->attachments; // decoded content included
});
```

## Dynamic servers

```php
// Database-backed: bind your own registry…
$this->app->singleton(ServerRegistry::class, DatabaseServerRegistry::class);

// …or connect ad hoc without any registration:
Postal::connect(new ServerConfig(name: 'tenant-42', url: $url, key: $key))->send($message);
```

## Testing your app

```php
use Cbox\LaravelPostal\Facades\Postal;

$fake = Postal::fake();

// ... code that sends ...

$fake->assertSent(fn ($message, $server) => $server === 'cbox-billing');
```

## Console DX

| Command | Purpose |
|---|---|
| `postal:install` | Publish config + setup checklist |
| `postal:doctor` | Full diagnosis — CI-friendly exit codes |
| `postal:ping` | Connectivity + credential check per server |
| `postal:tail` | Live-tail incoming webhook/inbound events |
| `postal:message` | Inspect one message + its delivery attempts |
| `postal:webhook-key` | Fetch the webhook signing key as PEM |

## Documentation

Full documentation lives in [docs/](docs/index.md): quickstart, multi-server
configuration, connection types (api / smtp-api / smtp), the webhook spine,
inbound mail, the event catalog, notifications, broadcasting, dynamic
server provisioning, extension points and the security model.

## Requirements

PHP 8.4+, Laravel 12 or 13. Tested against Postal 3.x.

## License

MIT — see [LICENSE.md](LICENSE.md).
