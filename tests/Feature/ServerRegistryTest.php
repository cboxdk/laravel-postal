<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\PostalManager;
use Cbox\LaravelPostal\Support\ServerConfig;
use Illuminate\Support\Facades\Http;

/**
 * Simulates a database-backed registry: server definitions come from an
 * arbitrary runtime source, not from config/postal.php.
 */
class ArrayServerRegistry implements ServerRegistry
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function find(string $name): ?ServerConfig
    {
        return isset($this->rows[$name])
            ? ServerConfig::fromArray($name, $this->rows[$name])
            : null;
    }

    public function names(): array
    {
        return array_keys($this->rows);
    }
}

it('resolves servers through a custom registry binding', function (): void {
    $registry = new ArrayServerRegistry;
    $registry->rows['tenant-1'] = ['url' => 'https://tenant1.postal.test', 'key' => 'tenant-1-key'];

    $this->app->instance(ServerRegistry::class, $registry);
    $this->app->forgetInstance('postal');
    Postal::clearResolvedInstances();

    Http::fake([
        'tenant1.postal.test/*' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['message_id' => 'db@postal', 'messages' => []],
        ]),
    ]);

    $manager = $this->app->make(Factory::class);

    expect($manager->names())->toBe(['tenant-1']);

    $manager->server('tenant-1')->send(SendMessage::create()->to('a@b.c')->from('x@y.z'));

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Server-API-Key', 'tenant-1-key'));
});

it('drops cached connections with forget so rotated credentials take effect', function (): void {
    $registry = new ArrayServerRegistry;
    $registry->rows['tenant-1'] = ['url' => 'https://tenant1.postal.test', 'key' => 'old-key'];

    $this->app->instance(ServerRegistry::class, $registry);
    $this->app->forgetInstance('postal');
    Postal::clearResolvedInstances();

    $manager = $this->app->make(PostalManager::class);

    $before = $manager->server('tenant-1');

    $registry->rows['tenant-1']['key'] = 'new-key';

    expect($manager->server('tenant-1'))->toBe($before);

    $manager->forget('tenant-1');

    expect($manager->server('tenant-1'))->not->toBe($before);
});

it('builds ad hoc connections from explicit settings without any registry entry', function (): void {
    Http::fake([
        'adhoc.postal.test/*' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['message_id' => 'adhoc@postal', 'messages' => []],
        ]),
    ]);

    $connection = Postal::connect(new ServerConfig(
        name: 'adhoc',
        url: 'https://adhoc.postal.test',
        key: 'adhoc-key',
    ));

    expect($connection)->toBeInstanceOf(Connection::class)
        ->and($connection->name())->toBe('adhoc');

    $connection->send(SendMessage::create()->to('a@b.c')->from('x@y.z'));

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Server-API-Key', 'adhoc-key'));
});

it('keeps ad hoc fake connections assertable', function (): void {
    $fake = $this->fakePostal();

    $connection = Postal::connect(new ServerConfig(name: 'dynamic', url: 'https://x.test', key: 'k'));
    $connection->send(SendMessage::create()->to('a@b.c')->from('x@y.z'));

    $fake->assertSent(fn (SendMessage $message, string $server): bool => $server === 'dynamic');
});
