<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * A transport failure or HTTP 5xx response after exhausting retries.
 */
class ServerException extends PostalException {}
