<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Inbound;

use Cbox\LaravelPostal\Http\SignedDeliveryController;
use Cbox\LaravelPostal\Queue\SignedDeliveryJob;
use Cbox\LaravelPostal\Support\InboundConfig;
use Cbox\LaravelPostal\Support\ReceiverConfig;
use Illuminate\Http\Request;

/**
 * Receives inbound messages that a Postal route delivers to an HTTP
 * endpoint (`POST {path}/{server?}`). Deliveries are signed with the same
 * install-wide RSA key as webhooks; verification runs over the raw body
 * for both endpoint encodings (BodyAsJSON and FormData).
 *
 * The response drives Postal's route: 2xx marks the message delivered,
 * 5xx retries, other 4xx (including a signature rejection) hard-fails and
 * may bounce — so a misconfigured key stops inbound flow visibly.
 */
class InboundController extends SignedDeliveryController
{
    public function __construct(private readonly InboundConfig $inbound) {}

    protected function config(): ReceiverConfig
    {
        return $this->inbound;
    }

    protected function job(string $server, array $body): SignedDeliveryJob
    {
        return new ProcessInboundMessage($server, $body);
    }

    protected function parseBody(Request $request, string $rawBody): ?array
    {
        $body = parent::parseBody($request, $rawBody);

        // FormData encoding: the fields arrive form-urlencoded rather
        // than as a JSON document.
        return $body ?? $request->post();
    }
}
