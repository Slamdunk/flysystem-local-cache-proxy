<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache\Test;

use php_user_filter;
use RuntimeException;

/**
 * @internal
 */
final class StreamThatGoesWrongFilter extends php_user_filter
{
    private const FILTERNAME = 'gowrong';

    private static bool $filterRegistered = false;

    public static function register(): void
    {
        if (self::$filterRegistered) {
            return;
        }

        $success = stream_filter_register(self::FILTERNAME, __CLASS__);
        \assert(true === $success);
        self::$filterRegistered = true;
    }

    /**
     * @param resource $stream
     */
    public static function append($stream): void
    {
        $resource = stream_filter_append(
            $stream,
            self::FILTERNAME,
            STREAM_FILTER_ALL
        );
        \assert(false !== $resource);
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param ?int     $consumed
     * @param bool     $closing
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        throw new RuntimeException('Inner stream went wrong');
    }
}
