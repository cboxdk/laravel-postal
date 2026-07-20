# Changelog

All notable changes to `cboxdk/laravel-postal` are documented here.

## v0.1.0 — 2026-07-20

Initial release.

- Three connection types per server: `api` (structured JSON), `smtp-api`
  (raw RFC 2822 over the HTTP API) and `smtp` (classic SMTP submission via
  Symfony's transport, incl. SMTP-IP credentials and SMTP handshake ping).
- Inbound mail: Postal routes → signed HTTP endpoint → typed
  `PostalInboundMessage` events with an `InboundMessage` DTO (Hash and
  RawMessage formats, BodyAsJSON and FormData encodings), idempotent
  `direction = incoming` store rows.
- Dynamic server provisioning: `Contracts\ServerRegistry` (config-backed
  default, bindable to a database), `Postal::connect()` for ad hoc
  connections, `forget()`/`flush()` for credential rotation.

- Typed multi-server Postal client: structured + raw send, message lookup,
  delivery attempts, envelope unwrapping with typed exceptions (including the
  HTTP-200-with-`status: error` quirk and `parameter-error` envelopes),
  retries on transport errors / 429 / 5xx.
- `postal` mail transport (`MAIL_MAILER=postal`) with Postal message-id
  stamping (`X-Postal-Message-Id`) and `POSTAL_REDIRECT_TO` support.
- Verified webhook spine: raw-body RSA signature verification
  (SHA256-preferred, legacy SHA1, fail-closed), queued processing,
  uuid-based idempotency, `postal_messages` status store +
  `postal_message_events` log, and one typed Laravel event per Postal event
  type (including `SendLimitApproaching` / `SendLimitExceeded`).
- Notification channel with model linking (`notifiable` morph).
- Opt-in broadcasting on `postal.server.{name}` private channels — no
  broadcaster dependency.
- Console DX: `postal:ping`, `postal:tail`, `postal:message`,
  `postal:webhook-key` (JWKS → PEM).
- Optional `cboxdk/laravel-health` connection check.
- Testing support: `Postal::fake()`, `InteractsWithPostal`, fake connections
  with canned lookups.
- Fully-typed message lookups: selective expansions via the
  `MessageExpansion` enum, typed attachments, activity entries
  (opens/clicks), normalized headers and the decoded raw message.
- Guided setup: `postal:install` (publish + checklist), `postal:doctor`
  (full diagnosis with CI-friendly exit codes) and an LLM install prompt in
  the docs.
- End-to-end suite (`composer test:e2e` + CI workflow): spins up a real
  Postal 3.3.7 install in Docker (MariaDB + Mailpit relay sink, seeded via
  rails runner) and proves all three protocols, real signed webhooks and
  inbound delivery against genuine Postal behaviour.
