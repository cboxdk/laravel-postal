<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use InvalidArgumentException;

/**
 * SMTP submission settings for a server connection of type `smtp`.
 * Credentials map to a Postal SMTP credential; leave username/password null
 * for SMTP-IP (IP-authenticated) credentials.
 */
readonly class SmtpConfig
{
    public function __construct(
        public string $host,
        public int $port = 25,
        public ?string $username = null,
        public ?string $password = null,
        public bool $tls = false,
        public int $timeout = 30,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $host = Coerce::stringOrNull($config['host'] ?? null);

        if ($host === null || $host === '') {
            throw new InvalidArgumentException('SMTP configuration requires a host.');
        }

        return new self(
            host: $host,
            port: Coerce::int($config['port'] ?? null, 25),
            username: Coerce::stringOrNull($config['username'] ?? null),
            password: Coerce::stringOrNull($config['password'] ?? null),
            tls: Coerce::bool($config['tls'] ?? null),
            timeout: Coerce::int($config['timeout'] ?? null, 30),
        );
    }
}
