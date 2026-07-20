<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Tests\Feature;

use Cbox\LaravelPostal\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * The store is optional: with both store flags disabled the package must
 * register no migrations at all. Classic test class because the config has
 * to be in place before the provider boots.
 */
class OptionalMigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('postal.webhooks.store', false);
        $app['config']->set('postal.inbound.store', false);
    }

    public function test_no_tables_are_created_when_the_store_is_disabled(): void
    {
        $this->assertFalse(Schema::hasTable('postal_messages'));
        $this->assertFalse(Schema::hasTable('postal_message_events'));
    }
}
