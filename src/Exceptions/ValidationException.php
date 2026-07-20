<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * The request was rejected by Postal's validation — a `parameter-error`
 * envelope or a send error such as NoRecipients, NoContent, TooManyToAddresses,
 * FromAddressMissing, UnauthenticatedFromAddress or AttachmentMissingName.
 */
class ValidationException extends PostalException {}
