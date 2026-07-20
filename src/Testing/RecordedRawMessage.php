<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Testing;

/**
 * A raw send captured by the fake connection.
 */
readonly class RecordedRawMessage
{
    /**
     * @param  list<string>  $rcptTo
     */
    public function __construct(
        public string $server,
        public string $mailFrom,
        public array $rcptTo,
        public string $rawMessage,
        public bool $bounce,
    ) {}
}
