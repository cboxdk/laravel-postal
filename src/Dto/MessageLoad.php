<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\Timestamps;
use DateTimeImmutable;

/**
 * One open (tracking-pixel load) from the `activity_entries` expansion.
 */
readonly class MessageLoad
{
    /**
     * @param  string|null  $timestamp  As serialized by Postal (ISO 8601).
     */
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $timestamp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ipAddress: Coerce::stringOrNull($data['ip_address'] ?? null),
            userAgent: Coerce::stringOrNull($data['user_agent'] ?? null),
            timestamp: Coerce::stringOrNull($data['timestamp'] ?? null),
        );
    }

    public function occurredAt(): ?DateTimeImmutable
    {
        return Timestamps::parse($this->timestamp);
    }
}
