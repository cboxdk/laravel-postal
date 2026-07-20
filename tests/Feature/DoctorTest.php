<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeHealthyPings(): void
{
    Http::fake([
        '*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
    ]);
}

it('passes on a fully configured install', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
    config()->set('mail.mailers.postal', ['transport' => 'postal']);
    config()->set('mail.default', 'postal');

    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('Everything looks healthy')
        ->assertExitCode(0);
});

it('fails when the signing key is missing while verification is enabled', function (): void {
    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('POSTAL_WEBHOOK_PUBLIC_KEY')
        ->assertExitCode(1);
});

it('fails when the signing key is not valid PEM', function (): void {
    config()->set('postal.webhooks.public_key', 'not-a-pem');

    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('not valid PEM')
        ->assertExitCode(1);
});

it('fails when the default server does not exist', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
    config()->set('postal.default', 'missing');

    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('no such server')
        ->assertExitCode(1);
});

it('fails when a server rejects its API key', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());

    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
        'postal-second.test/*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'InvalidServerAPIKey', 'message' => 'The API token provided in X-Server-API-Key was not valid.'],
        ]),
    ]);

    $this->artisan('postal:doctor')->assertExitCode(1);
});

it('skips connectivity with --no-ping', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());

    Http::fake(['*' => Http::response('should never be called', 500)]);

    $this->artisan('postal:doctor', ['--no-ping' => true])->assertExitCode(0);

    Http::assertNothingSent();
});

it('reports a broken server definition instead of crashing', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
    config()->set('postal.servers.broken', ['type' => 'smtp']); // smtp without settings

    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('smtp settings')
        ->assertExitCode(1);
});

it('warns instead of failing when the store is disabled', function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
    config()->set('postal.webhooks.store', false);
    config()->set('postal.inbound.store', false);

    fakeHealthyPings();

    $this->artisan('postal:doctor')
        ->expectsOutputToContain('Store disabled')
        ->assertExitCode(0);
});

it('publishes config and prints the checklist via postal:install', function (): void {
    $this->artisan('postal:install')
        ->expectsOutputToContain('postal:doctor')
        ->assertExitCode(0);

    expect(file_exists(config_path('postal.php')))->toBeTrue();

    @unlink(config_path('postal.php'));
});
