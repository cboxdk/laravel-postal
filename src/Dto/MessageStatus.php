<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `status` expansion of a message lookup.
 */
readonly class MessageStatus
{
    public function __construct(
        public string $status,
        public ?float $lastDeliveryAttempt,
        public bool $held,
        public ?float $holdExpiry,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Coerce::string($data['status'] ?? null),
            lastDeliveryAttempt: Coerce::floatOrNull($data['last_delivery_attempt'] ?? null),
            held: Coerce::bool($data['held'] ?? null),
            holdExpiry: Coerce::floatOrNull($data['hold_expiry'] ?? null),
        );
    }
}
