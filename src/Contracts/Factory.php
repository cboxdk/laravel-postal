<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

/**
 * Resolves named Postal server connections.
 */
interface Factory
{
    /**
     * Get a connection to the given server, or the default server when null.
     */
    public function server(?string $name = null): Connection;

    /**
     * The names of all configured servers.
     *
     * @return list<string>
     */
    public function names(): array;
}
