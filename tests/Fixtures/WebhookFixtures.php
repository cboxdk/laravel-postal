<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Tests\Fixtures;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Reproduces Postal's webhook signing exactly as lib/postal/http.rb +
 * lib/postal/signer.rb do it: a strict-base64 RSA signature over the raw
 * body — SHA256 for X-Postal-Signature-256, SHA1 for X-Postal-Signature.
 *
 * Key pairs are real RSA keys generated once per test process — nothing
 * key-shaped is committed to the repository.
 */
class WebhookFixtures
{
    private static ?OpenSSLAsymmetricKey $signingKey = null;

    private static ?OpenSSLAsymmetricKey $otherKey = null;

    public static function publicKey(): string
    {
        return self::publicPemOf(self::signingKey());
    }

    public static function otherPublicKey(): string
    {
        return self::publicPemOf(self::otherKey());
    }

    public static function sign256(string $rawBody): string
    {
        return self::sign($rawBody, self::signingKey(), OPENSSL_ALGO_SHA256);
    }

    public static function signSha1(string $rawBody): string
    {
        return self::sign($rawBody, self::signingKey(), OPENSSL_ALGO_SHA1);
    }

    public static function signWithOtherKey(string $rawBody): string
    {
        return self::sign($rawBody, self::otherKey(), OPENSSL_ALGO_SHA256);
    }

    /**
     * A realistic MessageSent webhook body as Postal builds it in
     * WebhookDeliveryService#generate_payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function messageSentBody(array $overrides = []): string
    {
        $body = array_replace_recursive([
            'event' => 'MessageSent',
            'timestamp' => 1752969600.123,
            'payload' => [
                'message' => [
                    'id' => 4200,
                    'token' => 'AbCdEf123456',
                    'direction' => 'outgoing',
                    'message_id' => 'msgid-4200@postal.cbox.dk',
                    'to' => 'alice@example.com',
                    'from' => 'no-reply@cboxid.com',
                    'subject' => 'Welcome to Cbox',
                    'timestamp' => 1752969599.5,
                    'spam_status' => 'NotChecked',
                    'tag' => 'onboarding',
                ],
                'status' => 'Sent',
                'details' => 'Message for alice@example.com accepted by mx.example.com',
                'output' => '250 2.0.0 OK',
                'sent_with_ssl' => true,
                'timestamp' => 1752969600.1,
                'time' => 0.89,
            ],
            'uuid' => 'e4d2c9a0-1111-2222-3333-444455556666',
        ], $overrides);

        $json = json_encode($body);

        if ($json === false) {
            throw new RuntimeException('Could not encode webhook fixture body.');
        }

        return $json;
    }

    private static function signingKey(): OpenSSLAsymmetricKey
    {
        return self::$signingKey ??= self::generateKey();
    }

    private static function otherKey(): OpenSSLAsymmetricKey
    {
        return self::$otherKey ??= self::generateKey();
    }

    private static function generateKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new RuntimeException('Could not generate an RSA test key pair.');
        }

        return $key;
    }

    private static function publicPemOf(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);

        if ($details === false || ! isset($details['key']) || ! is_string($details['key'])) {
            throw new RuntimeException('Could not export the test public key.');
        }

        return $details['key'];
    }

    private static function sign(string $rawBody, OpenSSLAsymmetricKey $key, int $algorithm): string
    {
        $signature = '';

        if (! openssl_sign($rawBody, $signature, $key, $algorithm) || ! is_string($signature)) {
            throw new RuntimeException('Could not sign webhook fixture body.');
        }

        return base64_encode($signature);
    }
}
