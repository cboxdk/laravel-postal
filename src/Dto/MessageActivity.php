<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `activity_entries` expansion: every open and tracked click.
 */
readonly class MessageActivity
{
    /**
     * @param  list<MessageLoad>  $loads
     * @param  list<MessageClick>  $clicks
     */
    public function __construct(
        public array $loads,
        public array $clicks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $loads = [];
        $clicks = [];

        if (is_array($data['loads'] ?? null)) {
            foreach ($data['loads'] as $load) {
                if (is_array($load)) {
                    $loads[] = MessageLoad::fromArray(Coerce::map($load));
                }
            }
        }

        if (is_array($data['clicks'] ?? null)) {
            foreach ($data['clicks'] as $click) {
                if (is_array($click)) {
                    $clicks[] = MessageClick::fromArray(Coerce::map($click));
                }
            }
        }

        return new self(loads: $loads, clicks: $clicks);
    }
}
