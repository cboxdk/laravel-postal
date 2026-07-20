<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Inbound;

use Cbox\LaravelPostal\Contracts\InboundProcessor;
use Cbox\LaravelPostal\Queue\SignedDeliveryJob;

/**
 * The queued hop between the inbound controller (which must acknowledge
 * within Postal's endpoint timeout) and the processor.
 */
class ProcessInboundMessage extends SignedDeliveryJob
{
    public function handle(InboundProcessor $processor): void
    {
        $processor->process($this->server, InboundMessage::fromArray($this->body));
    }
}
