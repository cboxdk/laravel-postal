<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Models\PostalMessageEvent;
use InvalidArgumentException;

/**
 * Resolves the store's model classes through config so a host application
 * can subclass them (postal.models.*) while the package keeps the schema.
 */
class Models
{
    /**
     * @return class-string<PostalMessage>
     */
    public static function message(): string
    {
        return self::resolve('postal.models.message', PostalMessage::class);
    }

    /**
     * @return class-string<PostalMessageEvent>
     */
    public static function event(): string
    {
        return self::resolve('postal.models.event', PostalMessageEvent::class);
    }

    /**
     * @template TModel of object
     *
     * @param  class-string<TModel>  $base
     * @return class-string<TModel>
     */
    private static function resolve(string $key, string $base): string
    {
        $configured = config($key);

        if ($configured === null || $configured === $base) {
            return $base;
        }

        if (! is_string($configured) || ! is_a($configured, $base, true)) {
            $described = is_string($configured) ? $configured : get_debug_type($configured);

            throw new InvalidArgumentException(
                "Config [{$key}] must name {$base} or a subclass of it, got [{$described}].",
            );
        }

        return $configured;
    }
}
