<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Illuminate\Support\Facades\Http;

it('prints message details and deliveries via postal:message', function (): void {
    Http::fake([
        'postal.test/api/v1/messages/message' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => [
                'id' => 55,
                'token' => 'tok55',
                'status' => ['status' => 'Sent', 'held' => false],
                'details' => [
                    'rcpt_to' => 'alice@example.com',
                    'mail_from' => 'no-reply@cboxid.com',
                    'subject' => 'Hi there',
                    'message_id' => 'mid@postal',
                    'direction' => 'outgoing',
                    'size' => 1234,
                ],
            ],
        ]),
        'postal.test/api/v1/messages/deliveries' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => [[
                'id' => 1,
                'status' => 'Sent',
                'details' => 'accepted by mx',
                'output' => '250 OK',
                'sent_with_ssl' => true,
                'time' => 1.25,
                'timestamp' => 1752969600.0,
            ]],
        ]),
    ]);

    $this->artisan('postal:message', ['server' => 'default', 'id' => '55'])
        ->expectsOutputToContain('alice@example.com')
        ->expectsOutputToContain('250 OK')
        ->assertExitCode(0);
});

it('reports a missing message via postal:message', function (): void {
    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
    ]);

    $this->artisan('postal:message', ['server' => 'default', 'id' => '999'])->assertExitCode(1);
});

it('fetches and converts the webhook signing key via postal:webhook-key', function (): void {
    $pem = WebhookFixtures::publicKey();
    $key = openssl_pkey_get_public($pem);
    $details = $key !== false ? openssl_pkey_get_details($key) : false;

    if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
        $this->fail('Could not derive JWK from fixture key.');
    }

    $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    Http::fake([
        'postal.test/.well-known/jwks.json' => Http::response([
            'keys' => [[
                'kty' => 'RSA',
                'n' => $encode($details['rsa']['n']),
                'e' => $encode($details['rsa']['e']),
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'abc',
            ]],
        ]),
    ]);

    $this->artisan('postal:webhook-key')
        ->expectsOutputToContain('BEGIN PUBLIC KEY')
        ->assertExitCode(0);
});

it('fails cleanly when the JWKS endpoint is unavailable', function (): void {
    Http::fake(['postal.test/*' => Http::response('nope', 404)]);

    $this->artisan('postal:webhook-key')->assertExitCode(1);
});
