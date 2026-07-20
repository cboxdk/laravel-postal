<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Tests\Fixtures;

use Cbox\LaravelPostal\Testing\InteractsWithPostal;
use Orchestra\Testbench\TestCase;

/**
 * A composition site so PHPStan analyses the {@see InteractsWithPostal} trait
 * (a trait is only analysed where it is used). Mirrors how a host application
 * wires the trait into its own Testbench-based `TestCase`.
 */
class InteractsWithPostalFixture extends TestCase
{
    use InteractsWithPostal;
}
