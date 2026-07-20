<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal;

use Cbox\LaravelPostal\Client\ConnectionType;
use Cbox\LaravelPostal\Client\PostalClient;
use Cbox\LaravelPostal\Client\SmtpConnection;
use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Cbox\LaravelPostal\Support\HttpConfig;
use Cbox\LaravelPostal\Support\ServerConfig;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

/**
 * Resolves and caches one connection per server. Server settings come from
 * the bound ServerRegistry (config-backed by default — bind your own for
 * database-driven provisioning), or ad hoc via connect(). Calls made
 * directly on the manager proxy to the default server, so the facade works
 * without naming a server: Postal::send(...).
 *
 * @mixin Connection
 */
class PostalManager implements Factory
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ServerRegistry $registry,
        private readonly string $default,
        private readonly HttpConfig $httpConfig = new HttpConfig,
    ) {}

    public function server(?string $name = null): Connection
    {
        $name ??= $this->default;

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->registry->find($name);

        if ($config === null) {
            throw new InvalidArgumentException("Postal server [{$name}] is not configured.");
        }

        return $this->connections[$name] = $this->build($config);
    }

    /**
     * Build a connection from explicit settings, bypassing the registry —
     * for fully dynamic use (per-tenant credentials, ad hoc scripts). The
     * connection is not cached.
     */
    public function connect(ServerConfig $config): Connection
    {
        return $this->build($config);
    }

    public function names(): array
    {
        return $this->registry->names();
    }

    /**
     * Drop the cached connection for a server — call after rotating its
     * credentials in a dynamic registry.
     */
    public function forget(string $name): void
    {
        unset($this->connections[$name]);
    }

    /**
     * Drop every cached connection.
     */
    public function flush(): void
    {
        $this->connections = [];
    }

    /**
     * ServerConfig's constructor guarantees the invariants used here: api
     * types always have URL + key, and the smtp type always has settings.
     */
    private function build(ServerConfig $config): Connection
    {
        $api = $config->hasApi()
            ? new PostalClient($this->http, $config, $this->httpConfig)
            : null;

        return $config->type === ConnectionType::Smtp
            ? new SmtpConnection($config, $api)
            : $api ?? throw new InvalidArgumentException(
                "Postal server [{$config->name}] has no API credentials configured.",
            );
    }

    /**
     * Proxy calls to the default server's connection.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->server()->{$method}(...$arguments);
    }
}
