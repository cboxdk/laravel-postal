---
title: Threat model
weight: 52
description: What this package defends against — and honestly, what it does not.
---

# Threat model

## Defended

- **Forged webhooks.** RSA signature verification over the raw body, fail
  closed, no SHA1 downgrade when SHA256 is present. Unknown server names
  404 before any processing; unknown event types are dropped.
- **Webhook replay / redelivery.** Deduplication on Postal's per-request
  uuid before any side effect (store enabled). Every event exposes
  `uuid()` so listeners can dedupe too.
- **Credential misuse via response spoofing.** Postal error envelopes are
  mapped to typed exceptions; HTTP status codes alone are never trusted
  (Postal answers 200 for application errors).
- **Malformed input.** All payload hydration passes through typed coercion;
  `mixed` never reaches domain code. Malformed signatures and bodies are
  rejected without exceptions.

## Not defended (honest scope)

- **Transport security is your deployment's job.** Run Postal and the
  webhook endpoint behind TLS; the package does not pin certificates.
- **A compromised Postal install** can sign anything with its own key.
  Signature verification authenticates the *installation*, not individual
  mail servers — any server on the same install could be attributed via
  the URL suffix. Use distinct webhook URLs per server and treat the
  Postal admin as trusted infrastructure.
- **Replay across the dedupe window.** With the store disabled there is no
  dedupe state; listeners must tolerate redelivery.
- **API keys** are read from Laravel config/env; their storage and rotation
  are the host application's responsibility.
- **No rate limiting** is imposed on the webhook route beyond your own
  middleware group.
