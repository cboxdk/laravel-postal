<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

/**
 * The `status` attribute of a Postal API envelope. Postal always answers
 * HTTP 200 — this attribute, not the status code, carries the outcome.
 */
enum ApiStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case ParameterError = 'parameter-error';
}
