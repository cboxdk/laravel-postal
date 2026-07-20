<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * The operation needs a capability this connection does not have — e.g. a
 * message lookup on an smtp-type server with no API credential configured.
 */
class UnsupportedOperationException extends PostalException {}
