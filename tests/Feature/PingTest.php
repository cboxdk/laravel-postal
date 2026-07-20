<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Facades\Postal;
use Illuminate\Support\Facades\Http;

it('reports a healthy connection when the key is valid', function (): void {
    // A valid key looking up a non-existent id yields MessageNotFound — that
    // proves both reachability and authentication.
    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'error',
            'time' => 0.1,
            'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
    ]);

    $status = Postal::ping();

    expect($status->ok)->toBeTrue()
        ->and($status->server)->toBe('default')
        ->and($status->url)->toBe('https://postal.test')
        ->and($status->error)->toBeNull()
        ->and($status->roundTripMs)->toBeGreaterThanOrEqual(0.0);
});

it('reports an unhealthy connection on a bad key', function (): void {
    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'error',
            'time' => 0.1,
            'flags' => [],
            'data' => ['code' => 'InvalidServerAPIKey', 'message' => 'The API token provided in X-Server-API-Key was not valid.'],
        ]),
    ]);

    $status = Postal::ping();

    expect($status->ok)->toBeFalse()
        ->and($status->error)->toContain('X-Server-API-Key');
});

it('reports an unhealthy connection on server errors', function (): void {
    Http::fake(['postal.test/*' => Http::response('boom', 500)]);

    expect(Postal::ping()->ok)->toBeFalse();
});

it('prints a ping table for all servers via postal:ping', function (): void {
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

    $this->artisan('postal:ping')
        ->expectsOutputToContain('postal-second.test')
        ->assertExitCode(1);
});

it('pings a single named server via postal:ping', function (): void {
    Http::fake([
        'postal.test/*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
    ]);

    $this->artisan('postal:ping', ['server' => 'default'])->assertExitCode(0);

    Http::assertSentCount(1);
});
