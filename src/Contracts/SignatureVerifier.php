<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

/**
 * Verifies Postal's RSA webhook signature over the raw request body.
 */
interface SignatureVerifier
{
    /**
     * @param  string  $rawBody  The raw, unmodified request body bytes.
     * @param  string|null  $signature256  Base64 value of X-Postal-Signature-256 (RSA-SHA256).
     * @param  string|null  $signatureSha1  Base64 value of X-Postal-Signature (legacy RSA-SHA1).
     */
    public function verify(string $rawBody, ?string $signature256, ?string $signatureSha1): bool;
}
