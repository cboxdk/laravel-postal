<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * A single delivery attempt for a message (/api/v1/messages/deliveries).
 */
readonly class Delivery
{
    public function __construct(
        public int $id,
        public string $status,
        public ?string $details,
        public ?string $output,
        public bool $sentWithSsl,
        public ?string $logId,
        public ?float $time,
        public ?float $timestamp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Coerce::int($data['id'] ?? null),
            status: Coerce::string($data['status'] ?? null),
            details: Coerce::stringOrNull($data['details'] ?? null),
            output: Coerce::stringOrNull($data['output'] ?? null),
            sentWithSsl: Coerce::bool($data['sent_with_ssl'] ?? null),
            logId: Coerce::stringOrNull($data['log_id'] ?? null),
            time: Coerce::floatOrNull($data['time'] ?? null),
            timestamp: Coerce::floatOrNull($data['timestamp'] ?? null),
        );
    }
}
