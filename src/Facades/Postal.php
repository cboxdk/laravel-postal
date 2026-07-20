<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Facades;

use Cbox\LaravelPostal\Client\ConnectionStatus;
use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Cbox\LaravelPostal\Dto\Delivery;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendResult;
use Cbox\LaravelPostal\PostalManager;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Testing\PostalFake;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static Connection server(?string $name = null)
 * @method static Connection connect(\Cbox\LaravelPostal\Support\ServerConfig $config)
 * @method static list<string> names()
 * @method static void forget(string $name)
 * @method static void flush()
 * @method static SendResult send(SendMessage $message)
 * @method static SendResult sendRaw(string $mailFrom, list<string> $rcptTo, string $rawMessage, bool $bounce = false)
 * @method static MessageDetails message(int $id, true|list<\Cbox\LaravelPostal\Dto\MessageExpansion> $expansions = true)
 * @method static list<Delivery> deliveries(int $id)
 * @method static ConnectionStatus ping()
 *
 * @see PostalManager
 */
class Postal extends Facade
{
    public static function fake(): PostalFake
    {
        $app = static::getFacadeApplication();

        if (! $app instanceof Container) {
            throw new RuntimeException('The Postal facade has no application set; boot Laravel before faking.');
        }

        $config = $app->make(ConfigRepository::class);

        // Names come from the bound ServerRegistry, not raw config, so the
        // fake mirrors the real manager under custom (e.g. database-backed)
        // registries too.
        $fake = new PostalFake(
            Coerce::string($config->get('postal.default'), 'default'),
            $app->make(ServerRegistry::class)->names(),
        );

        // swap() also refreshes the facade's resolved-instance cache, so a
        // manager touched before fake() cannot linger behind the fake.
        static::swap($fake);
        $app->instance(Factory::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'postal';
    }
}
