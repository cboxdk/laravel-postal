<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * The shared posture of the two signed-delivery endpoints (webhooks and
 * inbound): route, verification, queueing and storage.
 */
abstract readonly class ReceiverConfig
{
    /**
     * @param  list<string>  $middleware
     */
    public function __construct(
        public bool $enabled,
        public string $path,
        public array $middleware,
        public bool $verifySignature,
        public ?string $queue,
        public ?string $connection,
        public bool $store,
    ) {}
}
