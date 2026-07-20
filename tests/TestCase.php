<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Tests;

use Cbox\LaravelPostal\LaravelPostalServiceProvider;
use Cbox\LaravelPostal\Testing\InteractsWithPostal;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use InteractsWithPostal;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelPostalServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('postal.default', 'default');
        $app['config']->set('postal.servers', [
            'default' => [
                'url' => 'https://postal.test',
                'key' => 'test-api-key',
            ],
            'second' => [
                'url' => 'https://postal-second.test',
                'key' => 'second-api-key',
            ],
        ]);
        $app['config']->set('postal.http.retry.sleep_ms', 0);
    }
}
