<?php

declare(strict_types=1);

use Cbox\LaravelHealth\Enums\Status;
use Cbox\LaravelPostal\Health\PostalConnectionCheck;
use Illuminate\Support\Facades\Http;

it('reports ok when every server pings healthy', function (): void {
    Http::fake([
        '*' => Http::response([
            'status' => 'error', 'time' => 0.1, 'flags' => [],
            'data' => ['code' => 'MessageNotFound', 'message' => 'No message found matching provided ID'],
        ]),
    ]);

    $result = app(PostalConnectionCheck::class)->run();

    expect($result->status)->toBe(Status::Ok)
        ->and($result->name)->toBe('postal')
        ->and($result->metadata)->toHaveKeys(['default', 'second']);
});

it('reports critical when a server has a bad key', function (): void {
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

    $result = app(PostalConnectionCheck::class)->run();

    expect($result->status)->toBe(Status::Critical)
        ->and($result->message)->toContain('second');
});
