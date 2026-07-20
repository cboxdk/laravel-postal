# Build status

Living status doc for `cboxdk/laravel-postal`. Updated 2026-07-20
(post ship-readiness review — verdict: ready to ship as v0.1.0).

## Ship-readiness review (4-angle, all findings fixed)

- Deadlock resilience: store transaction retries 3×, SignedDeliveryJob
  `$tries = 3` (the controller has already ACKed Postal — a transient
  failure must not lose the delivery). Rollback-on-failure now has direct
  regression tests (TransactionalStoreTest) for both pipelines.
- `Postal::fake()` seeds names from the bound ServerRegistry (parity with
  custom registries); SMTP Message-ID extraction limited to the header
  block; configurable SMTP socket timeout (`smtp.timeout`, default 30s).
- No private keys in the repo: test keypairs are generated per process.
- E2E: host ports parameterized (`E2E_WEB_PORT` etc.), `CAPTURE_PORT`
  propagated to the seed; clean-slate run re-verified green (12/12).
- Docs: broadcast Echo sample fixed to the real payload shape; inbound
  `rawMessage()` prose aligned; smtp.timeout documented.

## Review round (8-angle multi-agent review, all confirmed findings fixed)

- Transactional, race-free store processing (dedupe rollback on failure,
  insertOrIgnore + row locks, atomic counters) via the shared MessageStore.
- Fake `names()` now mirrors configured servers; `Postal::fake()` uses
  facade swap so a pre-resolved manager can't linger.
- Bounce flag propagates on smtp-api sends and refuses loudly on smtp.
- Inbound: id-less payloads get content-hash dedupe keys; events carry the
  dedupe key as `uuid()`; attachments/raw MIME decode lazily.
- Broadcast payloads are identifiers-only (no bodies/attachments/MIME).
- Mail transport resolves its connection per send — `Postal::forget()`
  covers Octane/queue workers with cached mailers.
- Shared SignedDeliveryController unifies the signature-verification path
  for webhooks + inbound; envelope addresses parse via symfony/mime.
- `postal:doctor` checks the actual router (stale route-cache detection);
  `postal:tail --once` shows the latest batch; (server, id) index added;
  `ext-openssl` declared; graceful SIGINT in `postal:tail`.

## Done (all verified against Postal 3.3.7 source + QA gate green)

- **Client** — multi-server manager, typed envelope handling (incl.
  HTTP-200-with-error and `parameter-error`), typed exceptions, retries,
  selective message expansions (fully typed: attachments, activity,
  headers, raw message), deliveries, ping handshake.
- **Connection types** — `api`, `smtp-api` (MIME via /send/raw),
  `smtp` (Symfony EsmtpTransport, SMTP-IP supported, handshake ping).
- **Dynamic servers** — `ServerRegistry` contract (config default,
  DB-bindable), `Postal::connect()`, `forget()`/`flush()`.
- **Webhook spine** — raw-body RSA verify (SHA256-pinned, SHA1 legacy,
  fail closed), queued processing, uuid dedupe, status store + event log,
  10 typed events (incl. SendLimit*).
- **Inbound** — signed HTTP endpoint, Hash + RawMessage formats,
  BodyAsJSON + FormData encodings, `PostalInboundMessage`, incoming store
  rows, id dedupe.
- **Mail transport** — `MAIL_MAILER=postal`, message-id stamping,
  `redirect_to`, per-mailer server selection.
- **Notifications** — `postal` channel, notifiable model linking.
- **Broadcasting** — opt-in, no broadcaster dependency.
- **Console** — install, doctor, ping, tail, message, webhook-key.
- **Testing support** — `Postal::fake()`, `InteractsWithPostal`, fake
  connections with canned lookups (dogfooded by the suite).
- **Docs** — topic-folder layout, `metadata_quality: complete`, LLM
  install prompt.
- **Supply chain** — license check, CycloneDX SBOM, CI matrix
  (PHP 8.4/8.5 × Laravel 12/13).

## E2E suite (composer test:e2e, .github/workflows/e2e.yml)

Spins up real Postal 3.3.7 (MariaDB + Mailpit relay sink) in Docker,
seeded via rails runner. 12 tests, verified green locally from a clean
slate: API protocol + real error codes, SMTP submission + handshake,
a really-signed MessageSent webhook verified with the live JWKS key and
replayed through the full package spine, and inbound mail delivered over
SMTP → route → signed HTTP endpoint → `/postal/inbound`. The suite caught
one real bug (uninitialized `SentMessage::$messageId` on raw SMTP sends) —
fixed by extracting the Message-ID from the submitted bytes.

## Known gaps / decisions

- No Postal admin API exists — org/server/domain/webhook/route management
  stays a manual web-UI step (flagged in docs + LLM prompt). Verified
  against 3.3.7 routes and Postal main; revisit if upstream ships one.
- Inbound via direct SMTP into the app (an SMTP listener) is out of scope;
  inbound arrives via Postal routes → HTTP endpoint.
- Signature conformance is proven twice: unit-level against Postal's exact
  signing algorithm with real RSA keypairs, and e2e against a live
  install's actual signed deliveries.

## Release state

- Nothing committed/tagged yet. First release should be `v0.1.0`
  (CHANGELOG ready). Docs ship with tagged releases (cbox-web scrapes
  tags).
