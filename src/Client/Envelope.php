<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

use Cbox\LaravelPostal\Exceptions\PostalException;
use Cbox\LaravelPostal\Support\Coerce;

/**
 * The `{status, time, flags, data}` wrapper around every Postal API response.
 */
readonly class Envelope
{
    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public ApiStatus $status,
        public float $time,
        public array $flags,
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromArray(array $response): self
    {
        $status = $response['status'] ?? null;
        $parsed = is_string($status) ? ApiStatus::tryFrom($status) : null;

        if ($parsed === null) {
            throw new PostalException('Postal returned a response without a recognisable status envelope.');
        }

        return new self(
            status: $parsed,
            time: Coerce::float($response['time'] ?? null),
            flags: Coerce::map($response['flags'] ?? null),
            data: Coerce::map($response['data'] ?? null),
        );
    }

    public function successful(): bool
    {
        return $this->status === ApiStatus::Success;
    }

    /**
     * The Postal error code carried in `data.code`, when this is an error envelope.
     */
    public function errorCode(): ?string
    {
        $code = $this->data['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    public function errorMessage(): ?string
    {
        $message = $this->data['message'] ?? null;

        return is_string($message) ? $message : null;
    }
}
