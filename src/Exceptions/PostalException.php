<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

use RuntimeException;

/**
 * Base exception for every Postal API failure. Carries the Postal error code
 * (e.g. "InvalidServerAPIKey") and the raw `data` object from the envelope.
 */
class PostalException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }
}
