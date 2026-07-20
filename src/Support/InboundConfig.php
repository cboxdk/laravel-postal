<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * The inbound (route → HTTP endpoint) receiving posture. Verification uses
 * the install-wide key from `postal.webhooks.public_key` — Postal signs
 * webhooks and inbound deliveries with the same key.
 */
readonly class InboundConfig extends ReceiverConfig
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: Coerce::bool($config['enabled'] ?? null, true),
            path: Coerce::string($config['path'] ?? null, 'postal/inbound'),
            middleware: Coerce::stringList($config['middleware'] ?? null, ['api']),
            verifySignature: Coerce::bool($config['verify_signature'] ?? null, true),
            queue: Coerce::stringOrNull($config['queue'] ?? null),
            connection: Coerce::stringOrNull($config['connection'] ?? null),
            store: Coerce::bool($config['store'] ?? null, true),
        );
    }
}
