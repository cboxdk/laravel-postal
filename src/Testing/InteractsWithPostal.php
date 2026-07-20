<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Testing;

use Cbox\LaravelPostal\Facades\Postal;

/**
 * Test helper: swap the Postal manager for an in-memory fake.
 */
trait InteractsWithPostal
{
    protected function fakePostal(): PostalFake
    {
        return Postal::fake();
    }
}
