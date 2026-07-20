<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Support\JwkConverter;
use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Cbox\LaravelPostal\Webhooks\RsaSignatureVerifier;

function jwkFromPem(string $pem): array
{
    $key = openssl_pkey_get_public($pem);

    if ($key === false) {
        throw new RuntimeException('Fixture public key did not load.');
    }

    $details = openssl_pkey_get_details($key);

    if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
        throw new RuntimeException('Fixture public key has no RSA details.');
    }

    $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return [
        'kty' => 'RSA',
        'n' => $encode($details['rsa']['n']),
        'e' => $encode($details['rsa']['e']),
        'use' => 'sig',
        'alg' => 'RS256',
    ];
}

it('round-trips a real RSA public key through JWK back to identical PEM', function (): void {
    // Real vector: take the fixture keypair's actual public key, express it
    // as the JWK Postal would serve, convert back, and require byte-equality.
    $pem = WebhookFixtures::publicKey();

    expect(JwkConverter::toPem(jwkFromPem($pem)))->toBe($pem);
});

it('produces a key that verifies real signatures', function (): void {
    $pem = JwkConverter::toPem(jwkFromPem(WebhookFixtures::publicKey()));

    $verifier = new RsaSignatureVerifier($pem);
    $body = WebhookFixtures::messageSentBody();

    expect($verifier->verify($body, WebhookFixtures::sign256($body), null))->toBeTrue();
});

it('rejects non-RSA JWKs', function (): void {
    JwkConverter::toPem(['kty' => 'EC', 'crv' => 'P-256']);
})->throws(InvalidArgumentException::class, 'Only RSA');

it('rejects JWKs without modulus or exponent', function (): void {
    JwkConverter::toPem(['kty' => 'RSA', 'n' => '', 'e' => 'AQAB']);
})->throws(InvalidArgumentException::class);
