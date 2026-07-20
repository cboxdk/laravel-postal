---
title: Dynamic servers
weight: 42
description: Provision servers at runtime — a database-backed ServerRegistry or ad hoc connections via Postal::connect().
---

# Dynamic servers

Server definitions do not have to live in `config/postal.php`. Two escape
hatches exist, both fully typed.

## A custom ServerRegistry (e.g. database-backed)

The manager resolves names through `Contracts\ServerRegistry`. Bind your
own implementation and every consumer — facade, mail transport,
notification channel, webhook/inbound attribution, `postal:ping` — sees
your servers:

```php
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Cbox\LaravelPostal\Support\ServerConfig;

class DatabaseServerRegistry implements ServerRegistry
{
    public function find(string $name): ?ServerConfig
    {
        $row = MailServer::query()->where('name', $name)->first();

        return $row === null ? null : ServerConfig::fromArray($name, [
            'url' => $row->url,
            'key' => $row->api_key,      // decrypt as needed
            'type' => $row->type,        // api | smtp-api | smtp
            'smtp' => $row->smtp_settings,
        ]);
    }

    public function names(): array
    {
        return MailServer::query()->pluck('name')->all();
    }
}

// In a service provider:
$this->app->singleton(ServerRegistry::class, DatabaseServerRegistry::class);
```

Connections are cached per name after first resolution. After rotating a
server's credentials, drop its cached connection:

```php
Postal::forget('tenant-1');   // or Postal::flush() for all
```

## Ad hoc connections

For one-off or per-tenant work where nothing should be registered at all:

```php
use Cbox\LaravelPostal\Support\ServerConfig;

$connection = Postal::connect(new ServerConfig(
    name: 'tenant-42',
    url: 'https://postal.cbox.dk',
    key: $tenant->postal_api_key,
));

$connection->send($message);
$connection->ping();
```

`connect()` bypasses the registry and never caches. Under `Postal::fake()`
ad hoc connections resolve to fakes keyed by the config name, so dynamic
sends stay assertable in tests.
