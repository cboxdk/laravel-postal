<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use InvalidArgumentException;

/**
 * Converts an RSA JWK (the shape Postal serves at /.well-known/jwks.json)
 * into SubjectPublicKeyInfo PEM for POSTAL_WEBHOOK_PUBLIC_KEY.
 *
 * This is pure DER/ASN.1 *encoding* of public parameters — no cryptographic
 * operations are implemented here; verification itself stays in OpenSSL.
 */
class JwkConverter
{
    private const string RSA_OID = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    /**
     * @param  array<string, mixed>  $jwk  An entry from a JWKS `keys` array.
     */
    public static function toPem(array $jwk): string
    {
        $kty = Coerce::stringOrNull($jwk['kty'] ?? null);

        if ($kty !== 'RSA') {
            throw new InvalidArgumentException('Only RSA JWKs are supported.');
        }

        $modulus = self::base64UrlDecode(Coerce::string($jwk['n'] ?? ''));
        $exponent = self::base64UrlDecode(Coerce::string($jwk['e'] ?? ''));

        if ($modulus === '' || $exponent === '') {
            throw new InvalidArgumentException('JWK is missing its RSA modulus or exponent.');
        }

        $rsaKey = self::sequence(self::integer($modulus).self::integer($exponent));
        $spki = self::sequence(self::RSA_OID.self::bitString($rsaKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            .'-----END PUBLIC KEY-----'."\n";
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private static function sequence(string $contents): string
    {
        return "\x30".self::length($contents).$contents;
    }

    private static function integer(string $bytes): string
    {
        // DER integers are signed big-endian: prefix a zero byte when the
        // high bit is set so the value stays positive.
        if ($bytes !== '' && (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::length($bytes).$bytes;
    }

    private static function bitString(string $contents): string
    {
        $padded = "\x00".$contents;

        return "\x03".self::length($padded).$padded;
    }

    private static function length(string $contents): string
    {
        $length = strlen($contents);

        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return pack('C', 0x80 | strlen($bytes)).$bytes;
    }
}
