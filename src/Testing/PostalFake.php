<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Testing;

use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Support\ServerConfig;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * A drop-in replacement for the PostalManager: every server resolves to an
 * in-memory FakeConnection with assertion helpers.
 *
 * @mixin Connection
 */
class PostalFake implements Factory
{
    /** @var array<string, FakeConnection> */
    private array $connections = [];

    /**
     * @param  list<string>  $configured  Server names names() should report
     *                                    before any connection is touched.
     */
    public function __construct(
        private readonly string $default = 'default',
        private readonly array $configured = [],
    ) {}

    public function server(?string $name = null): Connection
    {
        return $this->connection($name);
    }

    /**
     * The concrete fake for a server, for seeding canned lookups.
     */
    public function connection(?string $name = null): FakeConnection
    {
        $name ??= $this->default;

        return $this->connections[$name] ??= new FakeConnection($name);
    }

    /**
     * All configured server names plus any fake connection touched since —
     * mirrors PostalManager::names(), so code gated on names() (webhook and
     * inbound controllers, postal:ping) behaves identically under the fake.
     */
    public function names(): array
    {
        return array_values(array_unique([...$this->configured, ...array_keys($this->connections)]));
    }

    /**
     * Ad hoc connections resolve to the same fakes, keyed by config name,
     * so dynamically-provisioned sends stay assertable.
     */
    public function connect(ServerConfig $config): Connection
    {
        return $this->connection($config->name);
    }

    public function forget(string $name): void
    {
        unset($this->connections[$name]);
    }

    public function flush(): void
    {
        $this->connections = [];
    }

    /**
     * Assert at least one structured message was sent (on any server) —
     * optionally one matching the given filter.
     *
     * @param  (Closure(SendMessage, string): bool)|null  $filter  Receives the message and the server name.
     */
    public function assertSent(?Closure $filter = null): void
    {
        $matched = $this->sent($filter);

        Assert::assertNotEmpty(
            $matched,
            $filter === null
                ? 'No messages were sent through Postal.'
                : 'No sent Postal message matched the given filter.',
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent());
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame(
            [],
            $this->sent(),
            'Messages were sent through Postal unexpectedly.',
        );

        foreach ($this->connections as $connection) {
            Assert::assertSame([], $connection->sentRaw, 'Raw messages were sent through Postal unexpectedly.');
        }
    }

    /**
     * All structured messages sent across every server, optionally filtered.
     *
     * @param  (Closure(SendMessage, string): bool)|null  $filter
     * @return list<SendMessage>
     */
    public function sent(?Closure $filter = null): array
    {
        $messages = [];

        foreach ($this->connections as $server => $connection) {
            foreach ($connection->sent as $message) {
                if ($filter === null || $filter($message, $server)) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * All raw messages sent across every server.
     *
     * @return list<RecordedRawMessage>
     */
    public function sentRaw(): array
    {
        $messages = [];

        foreach ($this->connections as $connection) {
            foreach ($connection->sentRaw as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Proxy calls to the default server's fake connection.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }
}
