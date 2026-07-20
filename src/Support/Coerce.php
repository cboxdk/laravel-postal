<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

/**
 * Typed readers for untyped API payload values. All Postal DTO hydration goes
 * through these so `mixed` never leaks past the serialization boundary.
 */
class Coerce
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        return is_bool($value) ? $value : $default;
    }

    /**
     * A boolean that may arrive form-encoded as a string ("true", "1", …) —
     * Postal's FormData endpoint encoding stringifies every value.
     */
    public static function flag(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return match ($value) {
            'true', '1' => true,
            'false', '0', '' => false,
            default => $default,
        };
    }

    /**
     * The string entries of a list value; a bare string becomes a one-item
     * list. Anything else yields the default.
     *
     * @param  list<string>  $default
     * @return list<string>
     */
    public static function stringList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            return $value === '' ? $default : [$value];
        }

        if (! is_array($value)) {
            return $default;
        }

        $list = [];

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $list[] = $entry;
            }
        }

        return $list === [] ? $default : $list;
    }

    /**
     * A string-keyed array, or the default when the value is not an array.
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function mapOrNull(mixed $value): ?array
    {
        return is_array($value) ? self::map($value) : null;
    }
}
