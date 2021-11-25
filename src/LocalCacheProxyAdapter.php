<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;

final class LocalCacheProxyAdapter implements FilesystemAdapter
{
    private LocalFilesystemAdapter $localCacheAdapter;
    private PathPrefixer $pathPrefixer;
    public function __construct(
        private FilesystemAdapter $remoteAdapter,
        string $location
    ) {
        $this->localCacheAdapter = new LocalFilesystemAdapter($location);
        $this->pathPrefixer = new PathPrefixer($location, DIRECTORY_SEPARATOR);

        LocalCacheStreamFilter::register();
    }

    /**
     * {@inheritDoc}
     */
    public function fileExists(string $path): bool
    {
        return $this->localCacheAdapter->fileExists($path)
            || $this->remoteAdapter->fileExists($path);
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
        $this->localCacheAdapter->createDirectory(dirname($path), new Config());

        LocalCacheStreamFilter::appendWrite(
            $this->pathPrefixer->prefixPath($path),
            $contents
        );

        $this->remoteAdapter->writeStream($path, $contents, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $path): string
    {
        if ($this->localCacheAdapter->fileExists($path)) {
            return $this->localCacheAdapter->read($path);
        }

        return $this->remoteAdapter->read($path);
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
        $this->localCacheAdapter->delete($path);
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
        $this->localCacheAdapter->move(
            $source,
            $destination,
            $config
        );
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
        $this->localCacheAdapter->copy(
            $source,
            $destination,
            $config
        );
        $this->remoteAdapter->copy(
            $source,
            $destination,
            $config
        );
    }
}
