<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use Cbox\LaravelPostal\Client\ConnectionType;
use InvalidArgumentException;

/**
 * The connection details for a single Postal mail server. The type decides
 * the send channel: `api` and `smtp-api` need a URL + API key; `smtp` needs
 * SMTP settings (URL + key optional — they enable API lookups alongside).
 */
readonly class ServerConfig
{
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $key = null,
        public ConnectionType $type = ConnectionType::Api,
        public ?SmtpConfig $smtp = null,
    ) {
        if ($this->type->usesApi()) {
            if ($this->url === null || $this->url === '') {
                throw new InvalidArgumentException("Postal server [{$this->name}] has no URL configured.");
            }

            if ($this->key === null || $this->key === '') {
                throw new InvalidArgumentException("Postal server [{$this->name}] has no API key configured.");
            }
        }

        if ($this->type === ConnectionType::Smtp && $this->smtp === null) {
            throw new InvalidArgumentException(
                "Postal server [{$this->name}] uses the smtp connection type but has no smtp settings.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $type = Coerce::stringOrNull($config['type'] ?? null);
        $parsedType = $type === null ? ConnectionType::Api : ConnectionType::tryFrom($type);

        if ($parsedType === null) {
            throw new InvalidArgumentException(
                "Postal server [{$name}] has unknown connection type [{$type}] — expected api, smtp-api or smtp.",
            );
        }

        $url = Coerce::stringOrNull($config['url'] ?? null);
        $smtp = $config['smtp'] ?? null;

        return new self(
            name: $name,
            url: $url !== null ? rtrim($url, '/') : null,
            key: Coerce::stringOrNull($config['key'] ?? null),
            type: $parsedType,
            smtp: is_array($smtp) ? SmtpConfig::fromArray(Coerce::map($smtp)) : null,
        );
    }

    /**
     * Whether API operations (lookups, API sends, API ping) are available.
     */
    public function hasApi(): bool
    {
        return $this->url !== null && $this->key !== null;
    }
}
