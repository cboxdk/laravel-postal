<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Queue\SignedDeliveryJob;

/**
 * The queued hop between the webhook controller (which must acknowledge
 * within Postal's ~5s window) and the processor.
 */
class ProcessWebhook extends SignedDeliveryJob
{
    public function handle(WebhookProcessor $processor): void
    {
        $envelope = WebhookEnvelope::fromArray($this->body);

        if ($envelope !== null) {
            $processor->process($this->server, $envelope);
        }
    }
}
