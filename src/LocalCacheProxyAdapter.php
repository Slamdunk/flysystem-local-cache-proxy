<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class LocalCacheProxyAdapter implements FilesystemAdapter
{
    public function __construct(
        private FilesystemAdapter $remoteAdapter,
        private LocalFilesystemAdapter $localCacheAdapter
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function fileExists(string $path): bool
    {
        return $this->remoteAdapter->fileExists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->writeStream($path, $stream, $config);

        fclose($stream);
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->remoteAdapter->writeStream($path, $contents, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $path): string
    {
        $stream = $this->readStream($path);
        $contents = stream_get_contents($this->readStream($path));
        fclose($stream);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function readStream(string $path)
    {
        return $this->remoteAdapter->readStream($path);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): void
    {
        $this->remoteAdapter->delete($path);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $path): void
    {
        $this->remoteAdapter->deleteDirectory($path);
    }

    /**
     * {@inheritDoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->remoteAdapter->createDirectory($path, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->remoteAdapter->setVisibility($path, $visibility);
    }

    /**
     * {@inheritDoc}
     */
    public function visibility(string $path): FileAttributes
    {
        return $this->remoteAdapter->visibility($path);
    }

    /**
     * {@inheritDoc}
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->remoteAdapter->mimeType($path);
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->remoteAdapter->lastModified($path);
    }

    /**
     * {@inheritDoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->remoteAdapter->fileSize($path);
    }

    /**
     * {@inheritDoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->remoteAdapter->listContents($path, $deep);
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->remoteAdapter->move(
            $source,
            $destination,
            $config
        );
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->remoteAdapter->copy(
            $source,
            $destination,
            $config
        );
    }
}
