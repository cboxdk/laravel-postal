---
title: End-to-end suite
weight: 15
description: The e2e suite spins up a real Postal 3.3.7 install in Docker and drives all three protocols against it.
---

# End-to-end suite

Beyond the unit/feature suite (fakes and `Http::fake()`), the package
carries a true end-to-end suite that runs against a **real Postal 3.3.7
install** — the API client, SMTP submission, webhooks and inbound mail are
all exercised against genuine Postal behaviour, including its real RSA
signatures.

```bash
composer test:e2e     # ≈ e2e/run.sh — requires Docker
```

## What it spins up

`e2e/docker-compose.yml` starts MariaDB, Postal (web + SMTP + worker) and
[Mailpit](https://github.com/axllent/mailpit) as an SMTP sink. Postal
relays **all** outbound mail to the sink (`POSTAL_SMTP_RELAYS`), so
deliveries genuinely succeed and `MessageSent` webhooks genuinely fire.
Postal has no admin API, so `e2e/seed.rb` provisions the org, mail server,
API/SMTP credentials, verified domains, webhook and inbound route through
a `rails runner` inside the container. A tiny capture server
(`e2e/capture/server.php`) records Postal's signed webhook and
inbound-endpoint deliveries byte-for-byte.

## What it proves

- **API protocol** — ping semantics, structured + raw sends, typed
  expansions read back, real error envelopes (`InvalidServerAPIKey`,
  `MessageNotFound`, `NoRecipients`, `UnauthenticatedFromAddress`) mapped
  to the right exceptions.
- **SMTP protocol** — real handshake ping, authenticated submission that
  lands in the relay sink, API lookups on an smtp-type connection.
- **Webhooks** — a real, Postal-signed `MessageSent` delivery is captured;
  its signature is verified with the live JWKS key (via `JwkConverter`),
  tamper-rejection is asserted, and the exact bytes are replayed through
  the package's own route: controller → verifier → job → store → typed
  event.
- **Inbound** — a message is delivered *into* Postal over unauthenticated
  SMTP to a routed domain, Postal posts it (signed) to the HTTP endpoint,
  and the capture is replayed through `/postal/inbound` end to end.

## Running

```bash
./e2e/run.sh              # full cycle: up → seed → test → down
KEEP=1 ./e2e/run.sh       # keep the stack for inspection (Mailpit UI :18025)
SKIP_UP=1 ./e2e/run.sh    # reuse a stack kept running earlier
```

The suite lives in `tests/E2E` as its own PHPUnit testsuite; every test
self-skips unless the `POSTAL_E2E_*` env vars are present, so it is inert
in normal `composer test` runs. CI runs it in the dedicated `e2e`
workflow on every push and pull request.
