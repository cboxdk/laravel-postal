<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * The outcome of one doctor check.
 */
enum DoctorStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failure = 'failure';
}
