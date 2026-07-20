---
title: Requirements
weight: 3
description: PHP, Laravel and dependency requirements as enforced by Composer.
---

# Requirements

As enforced by `composer.json`:

| Requirement | Constraint |
|---|---|
| PHP | `^8.4` |
| ext-openssl | `*` (webhook signature verification) |
| illuminate/console, contracts, database, http, mail, queue, support | `^12.0 \|\| ^13.0` |
| symfony/mailer | `^7.4 \|\| ^8.0` |

Optional (suggested, not required):

| Package | Purpose |
|---|---|
| `cboxdk/laravel-health` `^2.0` | Enables the `PostalConnectionCheck` health check |

Tested against Postal **3.x** (developed and verified against 3.3.7).
