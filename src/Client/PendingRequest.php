<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

use Cbox\LaravelPostal\Exceptions\AuthenticationException;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Exceptions\PostalException;
use Cbox\LaravelPostal\Exceptions\RateLimitException;
use Cbox\LaravelPostal\Exceptions\ServerException;
use Cbox\LaravelPostal\Exceptions\UnsupportedOperationException;
use Cbox\LaravelPostal\Exceptions\ValidationException;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\HttpConfig;
use Cbox\LaravelPostal\Support\ServerConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * Executes authenticated requests against one Postal server: applies the API
 * key, retries transport errors / 429 / 5xx, unwraps the response envelope
 * and maps Postal error codes to typed exceptions.
 *
 * Note Postal's quirk: the legacy API answers HTTP 200 for application-level
 * errors, so the envelope's `status` attribute is authoritative — never the
 * HTTP status code alone.
 */
class PendingRequest
{
    /**
     * Postal error codes that indicate an authentication problem.
     */
    private const array AUTH_CODES = ['AccessDenied', 'InvalidServerAPIKey', 'ServerSuspended'];

    /**
     * Postal error codes raised by send/message validation.
     */
    private const array VALIDATION_CODES = [
        'ValidationError',
        'NoRecipients',
        'NoContent',
        'TooManyToAddresses',
        'TooManyCCAddresses',
        'TooManyBCCAddresses',
        'FromAddressMissing',
        'UnauthenticatedFromAddress',
        'AttachmentMissingName',
        'AttachmentMissingData',
    ];

    private readonly string $url;

    private readonly string $key;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ServerConfig $server,
        private readonly HttpConfig $config,
    ) {
        if ($server->url === null || $server->key === null) {
            throw new UnsupportedOperationException(
                "Postal server [{$server->name}] has no API URL/key configured — API operations are unavailable.",
            );
        }

        $this->url = $server->url;
        $this->key = $server->key;
    }

    /**
     * POST a JSON payload to the given API path and return the success envelope.
     *
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload): Envelope
    {
        try {
            $response = $this->http
                ->baseUrl($this->url)
                ->withHeaders(['X-Server-API-Key' => $this->key])
                ->acceptJson()
                ->timeout($this->config->timeout)
                ->retry(
                    $this->config->retryTimes,
                    $this->config->retrySleepMs,
                    fn (mixed $exception): bool => $this->isRetryable($exception),
                    throw: false,
                )
                ->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new ServerException(
                "Could not reach Postal server [{$this->server->name}] at {$this->url}: {$exception->getMessage()}",
            );
        }

        return $this->unwrap($response);
    }

    private function isRetryable(mixed $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    private function unwrap(Response $response): Envelope
    {
        if ($response->status() === 429) {
            throw new RateLimitException(
                "Postal server [{$this->server->name}] is rate limiting requests (HTTP 429).",
            );
        }

        if ($response->serverError()) {
            throw new ServerException(
                "Postal server [{$this->server->name}] returned HTTP {$response->status()}.",
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new ServerException(
                "Postal server [{$this->server->name}] returned a non-JSON response (HTTP {$response->status()}).",
            );
        }

        $envelope = Envelope::fromArray(Coerce::map($json));

        if ($envelope->successful()) {
            return $envelope;
        }

        throw $this->exceptionFor($envelope);
    }

    private function exceptionFor(Envelope $envelope): PostalException
    {
        if ($envelope->status === ApiStatus::ParameterError) {
            return new ValidationException(
                $envelope->errorMessage() ?? 'Postal rejected the request parameters.',
                'ParameterError',
                $envelope->data,
            );
        }

        $code = $envelope->errorCode();
        $message = $envelope->errorMessage()
            ?? ($code !== null ? "Postal returned error [{$code}]." : 'Postal returned an error.');

        return match (true) {
            in_array($code, self::AUTH_CODES, true) => new AuthenticationException($message, $code, $envelope->data),
            in_array($code, self::VALIDATION_CODES, true) => new ValidationException($message, $code, $envelope->data),
            $code === 'MessageNotFound' => new MessageNotFoundException($message, $code, $envelope->data),
            default => new PostalException($message, $code, $envelope->data),
        };
    }
}
