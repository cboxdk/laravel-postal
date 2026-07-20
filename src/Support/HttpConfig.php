<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * HTTP client behaviour shared by every server connection.
 */
readonly class HttpConfig
{
    public function __construct(
        public int $timeout = 15,
        public int $retryTimes = 3,
        public int $retrySleepMs = 200,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $retry = Coerce::map($config['retry'] ?? null);

        return new self(
            timeout: Coerce::int($config['timeout'] ?? null, 15),
            retryTimes: Coerce::int($retry['times'] ?? null, 3),
            retrySleepMs: Coerce::int($retry['sleep_ms'] ?? null, 200),
        );
    }
}
