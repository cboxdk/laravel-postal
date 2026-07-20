---
title: Health check
weight: 35
description: Monitor every configured Postal server with the optional cboxdk/laravel-health check.
---

# Health check

If your app uses `cboxdk/laravel-health`, this package ships a ready-made
check — it is optional and this package does not require the health
package.

```bash
composer require cboxdk/laravel-health
```

Register the check in `config/health.php`:

```php
'checks' => [
    // ...
    Cbox\LaravelPostal\Health\PostalConnectionCheck::class,
],
```

The check pings **every** configured server (real authenticated
round-trips) and reports:

- `ok` — all servers reachable with valid API keys, with per-server RTT in
  the metadata;
- `critical` — any server unreachable or rejecting its key, with the
  failing servers named in the message.

The same probe is available ad hoc as `php artisan postal:ping`.
