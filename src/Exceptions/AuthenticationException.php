<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * The X-Server-API-Key was missing, invalid, or the server is suspended
 * (Postal codes: AccessDenied, InvalidServerAPIKey, ServerSuspended).
 */
class AuthenticationException extends PostalException {}
