<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache;

use php_user_filter;

/**
 * @internal
 */
final class LocalCacheStreamFilter extends php_user_filter
{
    private const FILTERNAME = 'slamflysystemlocalcache.write';

    private string $filename;
    private string $tmpFilename;
    /** @var resource */
    private $resource;

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
    public static function appendWrite(string $filename, $stream): void
    {
        $resource = stream_filter_append(
            $stream,
            self::FILTERNAME,
            STREAM_FILTER_READ,
            $filename
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
        $consumed ??= 0;
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            \assert(\is_string($bucket->data));

            $contents = $bucket->data;
            stream_bucket_append($out, $bucket);
            \assert(\is_int($bucket->datalen));
            $consumed += $bucket->datalen;

            $result = fwrite($this->resource, $contents);
            \assert(false !== $result);
        }

        if ($closing) {
            $fclose = fclose($this->resource);
            \assert(false !== $fclose);
            $rename = rename($this->tmpFilename, $this->filename);
            \assert(false !== $rename);
        }

        return PSFS_PASS_ON;
    }

    public function onCreate(): bool
    {
        \assert(\is_string($this->params));
        $this->filename = $this->params;
        $this->tmpFilename = $this->filename.'.tmp';
        $this->resource = fopen($this->tmpFilename, 'w');

        return true;
    }
}
