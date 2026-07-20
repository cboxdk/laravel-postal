<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Client\ApiStatus;
use Cbox\LaravelPostal\Client\Envelope;
use Cbox\LaravelPostal\Exceptions\PostalException;

it('unwraps a success envelope', function (): void {
    $envelope = Envelope::fromArray([
        'status' => 'success',
        'time' => 0.123,
        'flags' => [],
        'data' => ['message_id' => 'abc@postal'],
    ]);

    expect($envelope->status)->toBe(ApiStatus::Success)
        ->and($envelope->successful())->toBeTrue()
        ->and($envelope->time)->toBe(0.123)
        ->and($envelope->data)->toBe(['message_id' => 'abc@postal']);
});

it('unwraps an error envelope with code and message', function (): void {
    $envelope = Envelope::fromArray([
        'status' => 'error',
        'time' => 0.01,
        'flags' => [],
        'data' => ['code' => 'InvalidServerAPIKey', 'message' => 'The API token provided in X-Server-API-Key was not valid.'],
    ]);

    expect($envelope->successful())->toBeFalse()
        ->and($envelope->errorCode())->toBe('InvalidServerAPIKey')
        ->and($envelope->errorMessage())->toContain('X-Server-API-Key');
});

it('recognises the parameter-error status', function (): void {
    $envelope = Envelope::fromArray([
        'status' => 'parameter-error',
        'time' => 0.01,
        'flags' => [],
        'data' => ['message' => '`id` parameter is required but is missing'],
    ]);

    expect($envelope->status)->toBe(ApiStatus::ParameterError)
        ->and($envelope->errorCode())->toBeNull()
        ->and($envelope->errorMessage())->toContain('`id` parameter');
});

it('rejects a response without a recognisable envelope', function (): void {
    Envelope::fromArray(['unexpected' => true]);
})->throws(PostalException::class);

it('tolerates missing time, flags and data', function (): void {
    $envelope = Envelope::fromArray(['status' => 'success']);

    expect($envelope->time)->toBe(0.0)
        ->and($envelope->flags)->toBe([])
        ->and($envelope->data)->toBe([]);
});
