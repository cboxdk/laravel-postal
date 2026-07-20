<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Inbound\InboundMessage;

/**
 * Processes one verified inbound HTTP-endpoint delivery: dedupe, persist,
 * dispatch.
 */
interface InboundProcessor
{
    /**
     * Returns the dispatched event, or null when the delivery was a
     * duplicate.
     */
    public function process(string $server, InboundMessage $message): ?PostalInboundMessage;
}
