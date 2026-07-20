<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Contracts\SignatureVerifier;
use OpenSSLAsymmetricKey;

/**
 * Verifies Postal's webhook signatures: an RSA signature over the raw request
 * body, sent as X-Postal-Signature-256 (SHA256) and X-Postal-Signature
 * (legacy SHA1) — see Postal's lib/postal/http.rb and lib/postal/signer.rb.
 *
 * The SHA256 header is preferred and, when present, is the only one checked —
 * a present-but-invalid SHA256 signature is rejected without falling back to
 * SHA1, closing the downgrade path. Malformed input never throws; it verifies
 * as false. With no public key configured, everything is rejected.
 */
class RsaSignatureVerifier implements SignatureVerifier
{
    public function __construct(private readonly ?string $publicKeyPem) {}

    public function verify(string $rawBody, ?string $signature256, ?string $signatureSha1): bool
    {
        $key = $this->publicKey();

        if ($key === null) {
            return false;
        }

        if ($signature256 !== null && $signature256 !== '') {
            return $this->verifyWith($key, $rawBody, $signature256, OPENSSL_ALGO_SHA256);
        }

        if ($signatureSha1 !== null && $signatureSha1 !== '') {
            return $this->verifyWith($key, $rawBody, $signatureSha1, OPENSSL_ALGO_SHA1);
        }

        return false;
    }

    private function verifyWith(OpenSSLAsymmetricKey $key, string $rawBody, string $signature, int $algorithm): bool
    {
        $decoded = base64_decode($signature, true);

        if ($decoded === false || $decoded === '') {
            return false;
        }

        return openssl_verify($rawBody, $decoded, $key, $algorithm) === 1;
    }

    private function publicKey(): ?OpenSSLAsymmetricKey
    {
        if ($this->publicKeyPem === null || trim($this->publicKeyPem) === '') {
            return null;
        }

        $key = openssl_pkey_get_public($this->publicKeyPem);

        return $key === false ? null : $key;
    }
}
