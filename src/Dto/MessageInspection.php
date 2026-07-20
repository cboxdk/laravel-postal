<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `inspection` expansion of a message lookup — spam and threat scanning.
 */
readonly class MessageInspection
{
    public function __construct(
        public bool $inspected,
        public bool $spam,
        public float $spamScore,
        public bool $threat,
        public ?string $threatDetails,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inspected: Coerce::bool($data['inspected'] ?? null),
            spam: Coerce::bool($data['spam'] ?? null),
            spamScore: Coerce::float($data['spam_score'] ?? null),
            threat: Coerce::bool($data['threat'] ?? null),
            threatDetails: Coerce::stringOrNull($data['threat_details'] ?? null),
        );
    }
}
