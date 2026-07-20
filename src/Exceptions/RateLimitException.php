<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * The server responded with HTTP 429 after exhausting retries.
 */
class RateLimitException extends PostalException {}
