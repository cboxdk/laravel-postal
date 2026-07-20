---
title: Artisan commands
weight: 14
description: Every console command the package ships — install, doctor, ping, tail, message, webhook-key.
---

# Artisan commands

| Command | What it does |
|---|---|
| `postal:install` | Publishes the config (add `--migrations` to copy migrations too) and prints the setup checklist. |
| `postal:doctor` | Diagnoses the whole setup: server definitions, signing key (PEM validity), routes, store tables, mailer wiring and live connectivity per server. `--no-ping` skips the network checks. Non-zero exit on failures — CI-friendly. |
| `postal:ping {server?}` | Real authenticated round-trip per server (API types) or an SMTP handshake (smtp type). Prints a table; non-zero exit when any server fails. |
| `postal:tail {server?} {--interval=2} {--once}` | Live-tails the webhook/inbound event log from the store — proves the spine with just a queue worker and a database. |
| `postal:message {server} {id}` | Fetches and pretty-prints one message with its delivery attempts. |
| `postal:webhook-key {server?}` | Fetches the install's webhook signing key from `/.well-known/jwks.json` and prints it as paste-ready PEM. |

## Typical flows

Fresh install:

```bash
php artisan postal:install
php artisan migrate
php artisan postal:ping
php artisan postal:webhook-key   # → POSTAL_WEBHOOK_PUBLIC_KEY
php artisan postal:doctor
```

Debugging "where did my email go":

```bash
php artisan postal:tail &        # watch events arrive
php artisan postal:message cbox-billing 4200
```

CI / deployment gate:

```bash
php artisan postal:doctor --no-ping   # config-only, no network
php artisan postal:doctor             # full, including connectivity
```
