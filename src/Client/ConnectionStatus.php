<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

/**
 * The result of a connection handshake: is the server reachable and is the
 * API key accepted?
 */
readonly class ConnectionStatus
{
    public function __construct(
        public string $server,
        public string $url,
        public bool $ok,
        public float $roundTripMs,
        public ?string $error = null,
    ) {}
}
