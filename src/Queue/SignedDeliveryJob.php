<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Base for the queued hop between a signed-delivery controller (which must
 * acknowledge within Postal's timeout) and its processor.
 */
abstract class SignedDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The controller has already ACKed the delivery to Postal, so a
     * transient failure here (deadlock victim, brief DB outage) must be
     * retried — the delivery is otherwise lost.
     */
    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly string $server,
        public readonly array $body,
    ) {}
}
