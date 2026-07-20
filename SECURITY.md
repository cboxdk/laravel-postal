# Security Policy

## Supported versions

Only the latest release receives security fixes.

## Reporting a vulnerability

Please report suspected vulnerabilities privately via
[GitHub Private Vulnerability Reporting](https://github.com/cboxdk/laravel-postal/security/advisories/new)
on this repository. Reports are handled on a best-effort basis.

Please do not open public issues for security reports.

## Scope notes

- Webhook authenticity relies on Postal's RSA signature over the raw request
  body. Verification is enabled by default and **fails closed**: with no
  configured public key, every delivery is rejected. The SHA256 header is
  preferred; a present-but-invalid SHA256 signature is rejected without
  falling back to the legacy SHA1 header.
- Signature verification uses OpenSSL only — this package implements no
  cryptographic primitives of its own.
- The package never manages Postal credentials; API keys are read from your
  application's configuration.
