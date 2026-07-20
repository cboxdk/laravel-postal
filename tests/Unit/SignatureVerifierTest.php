<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Cbox\LaravelPostal\Webhooks\RsaSignatureVerifier;

beforeEach(function (): void {
    $this->verifier = new RsaSignatureVerifier(WebhookFixtures::publicKey());
    $this->body = WebhookFixtures::messageSentBody();
});

it('accepts a valid SHA256 signature', function (): void {
    expect($this->verifier->verify($this->body, WebhookFixtures::sign256($this->body), null))->toBeTrue();
});

it('accepts a valid legacy SHA1 signature when no SHA256 header is present', function (): void {
    expect($this->verifier->verify($this->body, null, WebhookFixtures::signSha1($this->body)))->toBeTrue();
});

it('prefers SHA256 and does not fall back to SHA1 when SHA256 is invalid', function (): void {
    // A valid SHA1 signature must not rescue a bad SHA256 one — that would
    // reopen the downgrade path the 256 header exists to close.
    $result = $this->verifier->verify(
        $this->body,
        WebhookFixtures::signWithOtherKey($this->body),
        WebhookFixtures::signSha1($this->body),
    );

    expect($result)->toBeFalse();
});

it('rejects a tampered body', function (): void {
    $signature = WebhookFixtures::sign256($this->body);
    $tampered = str_replace('alice@example.com', 'mallory@evil.test', $this->body);

    expect($this->verifier->verify($tampered, $signature, null))->toBeFalse();
});

it('rejects a signature made with a different key', function (): void {
    expect($this->verifier->verify($this->body, WebhookFixtures::signWithOtherKey($this->body), null))->toBeFalse();
});

it('rejects malformed base64 without throwing', function (): void {
    expect($this->verifier->verify($this->body, '%%% not base64 %%%', null))->toBeFalse()
        ->and($this->verifier->verify($this->body, 'AAAA', null))->toBeFalse();
});

it('rejects when both signature headers are missing or empty', function (): void {
    expect($this->verifier->verify($this->body, null, null))->toBeFalse()
        ->and($this->verifier->verify($this->body, '', ''))->toBeFalse();
});

it('rejects everything when no public key is configured', function (): void {
    $verifier = new RsaSignatureVerifier(null);

    expect($verifier->verify($this->body, WebhookFixtures::sign256($this->body), null))->toBeFalse();
});

it('rejects everything when the configured key is not valid PEM', function (): void {
    $verifier = new RsaSignatureVerifier('not a pem');

    expect($verifier->verify($this->body, WebhookFixtures::sign256($this->body), null))->toBeFalse();
});
