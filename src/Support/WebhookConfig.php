<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * The webhook receiving posture. The public key is the Postal install's
 * webhook signing key — it also verifies inbound deliveries, which Postal
 * signs with the same key.
 */
readonly class WebhookConfig extends ReceiverConfig
{
    /**
     * @param  list<string>  $middleware
     */
    public function __construct(
        bool $enabled = true,
        string $path = 'postal/webhook',
        array $middleware = ['api'],
        bool $verifySignature = true,
        public ?string $publicKey = null,
        ?string $queue = null,
        ?string $connection = null,
        bool $store = true,
    ) {
        parent::__construct($enabled, $path, $middleware, $verifySignature, $queue, $connection, $store);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: Coerce::bool($config['enabled'] ?? null, true),
            path: Coerce::string($config['path'] ?? null, 'postal/webhook'),
            middleware: Coerce::stringList($config['middleware'] ?? null, ['api']),
            verifySignature: Coerce::bool($config['verify_signature'] ?? null, true),
            publicKey: Coerce::stringOrNull($config['public_key'] ?? null),
            queue: Coerce::stringOrNull($config['queue'] ?? null),
            connection: Coerce::stringOrNull($config['connection'] ?? null),
            store: Coerce::bool($config['store'] ?? null, true),
        );
    }
}
