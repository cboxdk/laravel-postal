---
title: Webhook signatures
weight: 51
description: Raw-body RSA verification, SHA256 preference without SHA1 downgrade, and fail-closed defaults.
---

# Webhook signatures

Postal signs every webhook POST with its install-wide RSA key over the
**raw request body**:

| Header | Algorithm |
|---|---|
| `X-Postal-Signature-256` | RSA-SHA256 |
| `X-Postal-Signature` | RSA-SHA1 (legacy) |
| `X-Postal-Signature-KID` | JWK key id of the signing key |

(Verified against Postal 3.3.7's `lib/postal/http.rb` and
`lib/postal/signer.rb`.)

## How the verifier behaves

- Verification runs over the **raw body bytes**, captured before any JSON
  parsing — re-serialized JSON would not verify.
- **SHA256 is preferred and pinned**: when the `-256` header is present it
  is the only one checked. A present-but-invalid SHA256 signature is
  rejected even if a valid SHA1 signature accompanies it — no downgrade.
- SHA1 is accepted only when the SHA256 header is absent (older Postal
  builds).
- **Fail closed**: no configured public key → every delivery rejected
  (401). Malformed signatures, bad base64 and broken PEM all verify as
  false, never as exceptions.
- Verification uses `openssl_verify` only; this package implements no
  cryptographic primitives. The JWK→PEM converter
  (`postal:webhook-key`) performs DER *encoding* of public parameters
  only.

## Inbound deliveries

Messages delivered by Postal routes to the inbound HTTP endpoint are
signed with the **same** install-wide key and verified by the same
verifier and rules. Note the failure semantics differ: a rejected inbound
delivery (4xx) hard-fails the message on Postal's side and may generate a
bounce, so a misconfigured key stops inbound flow visibly.

## Key management

The signing key's public half is served by Postal itself at
`/.well-known/jwks.json`. `php artisan postal:webhook-key` fetches and
converts it. Rotate by re-running the command; only one key is active per
Postal install.

## Disabling

`POSTAL_WEBHOOK_VERIFY=false` disables verification (local development
only). The test suite proves accept/reject behaviour against real RSA
keypairs and signatures produced exactly as Postal produces them.
