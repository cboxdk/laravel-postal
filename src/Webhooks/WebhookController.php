<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Http\SignedDeliveryController;
use Cbox\LaravelPostal\Queue\SignedDeliveryJob;
use Cbox\LaravelPostal\Support\ReceiverConfig;
use Cbox\LaravelPostal\Support\WebhookConfig;

/**
 * Receives Postal's webhook POSTs on `POST {path}/{server?}`. Multi-server:
 * point each Postal mail server's webhook at its named endpoint
 * (e.g. postal/webhook/cbox-billing); without the suffix the delivery is
 * attributed to the default server.
 */
class WebhookController extends SignedDeliveryController
{
    public function __construct(private readonly WebhookConfig $webhooks) {}

    protected function config(): ReceiverConfig
    {
        return $this->webhooks;
    }

    protected function job(string $server, array $body): SignedDeliveryJob
    {
        return new ProcessWebhook($server, $body);
    }
}
