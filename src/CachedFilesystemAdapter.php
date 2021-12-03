<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache;

use DateTimeInterface;
use League\Flysystem\FilesystemAdapter;

interface CachedFilesystemAdapter extends FilesystemAdapter
{
    public function touch(string $path, DateTimeInterface $date): void;

    public function clearCacheOlderThan(DateTimeInterface $date): void;
}
