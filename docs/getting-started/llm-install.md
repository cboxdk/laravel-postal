---
title: Install with an AI assistant
weight: 13
description: A copy-paste prompt that lets your AI assistant install and configure the package end to end.
---

# Install with an AI assistant

Paste the prompt below into your AI coding assistant (Claude Code, Cursor,
Copilot, …) from inside your Laravel project. It walks the assistant
through the whole setup — including the two steps that must happen in the
Postal web UI — and finishes by proving the install with `postal:doctor`.

````text
Install and configure the cboxdk/laravel-postal package (Postal mail server
integration for Laravel) in this Laravel application. Work step by step and
verify each step before continuing.

1. Run: composer require cboxdk/laravel-postal
   (Requires PHP 8.4+ and Laravel 12 or 13. Do not downgrade anything to
   make it fit — tell me instead.)

2. Run: php artisan postal:install
   then: php artisan migrate
   This publishes config/postal.php and creates the postal_messages +
   postal_message_events tables.

3. Ask me for: the Postal base URL, and for each mail server I want to use:
   a short name (e.g. "cbox-billing") and its API key (I create keys in the
   Postal web UI under my mail server → Credentials, type "API").
   Configure one entry per server under 'servers' in config/postal.php,
   reading secrets from .env — never hardcode keys. Set 'default' to the
   server I name as primary.
   - If I ask for SMTP submission instead of the HTTP API for a server,
     set 'type' => 'smtp' and fill its 'smtp' => [host, port, username,
     password] block from an SMTP credential.

4. Run: php artisan postal:ping
   Every configured server must show ✓. If not, show me the error and help
   me fix the URL/key before continuing.

5. Webhooks (delivery/bounce/open/click events):
   a. Ask me to add a webhook in the Postal web UI (my mail server →
      Webhooks) pointing at: https://MY-APP-URL/postal/webhook/SERVER-NAME
      — one per server, all events. Postal has no admin API, so this is a
      manual step for me; wait until I confirm.
   b. Run: php artisan postal:webhook-key
      and set the printed key in .env as POSTAL_WEBHOOK_PUBLIC_KEY
      (newlines escaped as \n, value in double quotes).

6. Inbound email (only if I want to receive mail): ask me to add a Route in
   the Postal web UI with an HTTP Endpoint (encoding "BodyAsJSON") pointing
   at: https://MY-APP-URL/postal/inbound/SERVER-NAME — then tell me that
   Cbox\LaravelPostal\Events\PostalInboundMessage events will fire.

7. Mail transport (only if I want Laravel's Mail to go through Postal):
   add a mailer 'postal' => ['transport' => 'postal'] to config/mail.php
   and set MAIL_MAILER=postal in .env.

8. Finish by running: php artisan postal:doctor
   Show me its full output. Fix every ✗ (red) finding and re-run until only
   ✓/⚠ remain, then summarize what was configured and which typed events
   (Cbox\LaravelPostal\Events\*) I can now listen for.

Reference documentation is in vendor/cboxdk/laravel-postal/docs/.
````

The assistant needs the two Postal-UI steps (webhook + inbound route) done
by you — Postal has no admin API — and the prompt tells it to pause and
ask. Everything else is fully automatable.
