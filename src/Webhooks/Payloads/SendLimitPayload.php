<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The SendLimitApproaching / SendLimitExceeded payload.
 */
readonly class SendLimitPayload
{
    public function __construct(
        public ServerInfo $server,
        public ?float $volume,
        public ?float $limit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            server: ServerInfo::fromArray(Coerce::map($payload['server'] ?? null)),
            volume: Coerce::floatOrNull($payload['volume'] ?? null),
            limit: Coerce::floatOrNull($payload['limit'] ?? null),
        );
    }
}
