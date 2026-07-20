<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Parses the timestamp representations Postal serializes — unix floats on
 * most payloads, ISO 8601 strings on activity entries.
 */
class Timestamps
{
    public static function parse(string|float|int|null $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value) || is_numeric($value)) {
            $parsed = DateTimeImmutable::createFromFormat('U.u', number_format((float) $value, 6, '.', ''));

            return $parsed === false ? null : $parsed;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
