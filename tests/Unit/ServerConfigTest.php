<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Client\ConnectionType;
use Cbox\LaravelPostal\Support\ServerConfig;

it('defaults to the api connection type', function (): void {
    $config = ServerConfig::fromArray('a', ['url' => 'https://postal.test/', 'key' => 'k']);

    expect($config->type)->toBe(ConnectionType::Api)
        ->and($config->url)->toBe('https://postal.test')
        ->and($config->hasApi())->toBeTrue();
});

it('parses an smtp-api server', function (): void {
    $config = ServerConfig::fromArray('a', ['url' => 'https://postal.test', 'key' => 'k', 'type' => 'smtp-api']);

    expect($config->type)->toBe(ConnectionType::SmtpApi)
        ->and($config->type->usesApi())->toBeTrue();
});

it('parses an smtp server without api credentials', function (): void {
    $config = ServerConfig::fromArray('a', [
        'type' => 'smtp',
        'smtp' => ['host' => 'postal.test', 'port' => 2525, 'username' => 'u', 'password' => 'p', 'tls' => true],
    ]);

    expect($config->type)->toBe(ConnectionType::Smtp)
        ->and($config->hasApi())->toBeFalse()
        ->and($config->smtp?->host)->toBe('postal.test')
        ->and($config->smtp->port)->toBe(2525)
        ->and($config->smtp->tls)->toBeTrue();
});

it('allows an smtp server to carry api credentials for lookups', function (): void {
    $config = ServerConfig::fromArray('a', [
        'type' => 'smtp',
        'url' => 'https://postal.test',
        'key' => 'k',
        'smtp' => ['host' => 'postal.test'],
    ]);

    expect($config->hasApi())->toBeTrue();
});

it('rejects api servers without a key', function (): void {
    ServerConfig::fromArray('a', ['url' => 'https://postal.test']);
})->throws(InvalidArgumentException::class, 'API key');

it('rejects smtp servers without smtp settings', function (): void {
    ServerConfig::fromArray('a', ['type' => 'smtp']);
})->throws(InvalidArgumentException::class, 'smtp settings');

it('rejects unknown connection types', function (): void {
    ServerConfig::fromArray('a', ['url' => 'u', 'key' => 'k', 'type' => 'pigeon']);
})->throws(InvalidArgumentException::class, 'pigeon');

it('rejects smtp settings without a host', function (): void {
    ServerConfig::fromArray('a', ['type' => 'smtp', 'smtp' => ['port' => 25]]);
})->throws(InvalidArgumentException::class, 'host');
