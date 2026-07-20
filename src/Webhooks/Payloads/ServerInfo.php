<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `server` hash Postal embeds in DomainDNSError and SendLimit* payloads.
 */
readonly class ServerInfo
{
    public function __construct(
        public ?string $uuid,
        public ?string $name,
        public ?string $permalink,
        public ?string $organization,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Coerce::stringOrNull($data['uuid'] ?? null),
            name: Coerce::stringOrNull($data['name'] ?? null),
            permalink: Coerce::stringOrNull($data['permalink'] ?? null),
            organization: Coerce::stringOrNull($data['organization'] ?? null),
        );
    }
}
