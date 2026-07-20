<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Http;

use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\SignatureVerifier;
use Cbox\LaravelPostal\Queue\SignedDeliveryJob;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\ReceiverConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared receiving path for Postal's signed HTTP deliveries (webhooks
 * and inbound messages): resolve the server from the URL, verify the RSA
 * signature over the raw body, queue processing, and acknowledge
 * immediately — Postal expects a 2xx within a few seconds and retries with
 * backoff otherwise.
 *
 * Keeping this in one place means a fix to the verification path can never
 * land on one endpoint and miss the other.
 */
abstract class SignedDeliveryController
{
    public function __invoke(
        Request $request,
        Factory $postal,
        SignatureVerifier $verifier,
        ?string $server = null,
    ): JsonResponse {
        $config = $this->config();
        $server ??= Coerce::string(config('postal.default'), 'default');

        if (! in_array($server, $postal->names(), true)) {
            return new JsonResponse(['status' => 'unknown-server'], 404);
        }

        $rawBody = (string) $request->getContent();

        if ($config->verifySignature) {
            $verified = $verifier->verify(
                $rawBody,
                $request->headers->get('X-Postal-Signature-256'),
                $request->headers->get('X-Postal-Signature'),
            );

            if (! $verified) {
                return new JsonResponse(['status' => 'invalid-signature'], 401);
            }
        }

        $body = $this->parseBody($request, $rawBody);

        if ($body === null || $body === []) {
            return new JsonResponse(['status' => 'invalid-payload'], 400);
        }

        $this->dispatch($server, Coerce::map($body), $config);

        return new JsonResponse(['status' => 'ok']);
    }

    abstract protected function config(): ReceiverConfig;

    /**
     * Build the processing job for one verified delivery.
     *
     * @param  array<string, mixed>  $body
     */
    abstract protected function job(string $server, array $body): SignedDeliveryJob;

    /**
     * Parse the verified raw body. Null means unparseable → 400.
     *
     * @return array<mixed>|null
     */
    protected function parseBody(Request $request, string $rawBody): ?array
    {
        $body = json_decode($rawBody, true);

        return is_array($body) ? $body : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function dispatch(string $server, array $body, ReceiverConfig $config): void
    {
        $job = $this->job($server, $body);

        if ($config->connection !== null) {
            $job->onConnection($config->connection);
        }

        if ($config->queue !== null) {
            $job->onQueue($config->queue);
        }

        dispatch($job);
    }
}
