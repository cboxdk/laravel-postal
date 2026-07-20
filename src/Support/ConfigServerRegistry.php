<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The default server registry: reads `postal.servers` from config on every
 * call, so runtime config changes are honoured.
 */
class ConfigServerRegistry implements ServerRegistry
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function find(string $name): ?ServerConfig
    {
        $servers = Coerce::map($this->config->get('postal.servers', []));
        $entry = $servers[$name] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        return ServerConfig::fromArray($name, Coerce::map($entry));
    }

    public function names(): array
    {
        return array_keys(Coerce::map($this->config->get('postal.servers', [])));
    }
}
