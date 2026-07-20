<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Exceptions;

/**
 * No message matched the provided ID (Postal code: MessageNotFound).
 */
class MessageNotFoundException extends PostalException {}
