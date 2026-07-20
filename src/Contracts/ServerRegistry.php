<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

use Cbox\LaravelPostal\Support\ServerConfig;

/**
 * Resolves server names to connection settings. The default implementation
 * reads `config('postal.servers')`; bind your own (e.g. database-backed) to
 * provision servers dynamically:
 *
 *     $this->app->singleton(ServerRegistry::class, DatabaseServerRegistry::class);
 */
interface ServerRegistry
{
    public function find(string $name): ?ServerConfig;

    /**
     * All known server names.
     *
     * @return list<string>
     */
    public function names(): array;
}
