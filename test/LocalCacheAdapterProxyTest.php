<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache\Test;

use DateTimeImmutable;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\EmptyExtensionToMimeTypeMap;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use RuntimeException;
use SlamFlysystem\LocalCache\CachedFilesystemAdapter;
use SlamFlysystem\LocalCache\LocalCacheProxyAdapter;

/**
 * @covers \SlamFlysystem\LocalCache\LocalCacheProxyAdapter
 *
 * @internal
 */
final class LocalCacheAdapterProxyTest extends FilesystemAdapterTestCase
{
    protected ?CachedFilesystemAdapter $customAdapter = null;
    protected LocalFilesystemAdapter $remoteAdapter;
    protected LocalFilesystemAdapter $localCacheAdapter;
    protected string $remoteRoot;
    protected string $localRoot;

    protected function setUp(): void
    {
        $testToken = (int) getenv('TEST_TOKEN');
        $this->remoteRoot = __DIR__.'/assets/'.$testToken.'_remote-root';
        $this->localRoot = __DIR__.'/assets/'.$testToken.'_local-root';
        reset_function_mocks();
        delete_directory($this->remoteRoot);
        delete_directory($this->localRoot);
    }

    protected function tearDown(): void
    {
        reset_function_mocks();
        delete_directory($this->remoteRoot);
        delete_directory($this->localRoot);
    }

    public function adapter(): CachedFilesystemAdapter
    {
        if (null === $this->customAdapter) {
            $this->localCacheAdapter = new LocalFilesystemAdapter($this->localRoot);
            $this->customAdapter = new LocalCacheProxyAdapter(
                $this->remoteAdapter = new LocalFilesystemAdapter($this->remoteRoot),
                $this->localRoot
            );
        }

        return $this->customAdapter;
    }

    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->customAdapter = new LocalCacheProxyAdapter(
            new LocalFilesystemAdapter(
                $this->remoteRoot,
                null,
                LOCK_EX,
                LocalFilesystemAdapter::DISALLOW_LINKS,
                new ExtensionMimeTypeDetector(new EmptyExtensionToMimeTypeMap())
            ),
            $this->localRoot
        );

        parent::fetching_unknown_mime_type_of_a_file();
    }

    /**
     * @test
     */
    public function writing_saves_a_local_copy(): void
    {
        $adapter = $this->adapter();

        $adapter->write('path.txt', 'contents', new Config());

        $fileExists = $adapter->fileExists('path.txt');
        static::assertTrue($fileExists);

        $contents = $adapter->read('path.txt');
        static::assertSame('contents', $contents);

        $fileExists = $this->localCacheAdapter->fileExists('path.txt');
        static::assertTrue($fileExists);

        $contents = $this->localCacheAdapter->read('path.txt');
        static::assertSame('contents', $contents);
    }

    /**
     * @test
     */
    public function stream_that_goes_wrong_doesnt_create_a_false_positive_local_file(): void
    {
        $adapter = $this->adapter();

        StreamThatGoesWrongFilter::register();

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, 'contents');
        rewind($stream);

        StreamThatGoesWrongFilter::append($stream);

        try {
            $adapter->writeStream('path.txt', $stream, new Config());
            static::fail();
        } catch (RuntimeException $runtimeException) {
        } finally {
        }

        try {
            fclose($stream);
        } catch (RuntimeException $runtimeException) {
        }

        $fileExists = $this->localCacheAdapter->fileExists('path.txt');
        static::assertFalse($fileExists);
    }

    /**
     * @test
     */
    public function file_exists_reply_with_local_cache_first(): void
    {
        $adapter = $this->adapter();

        $adapter->write('path.txt', 'contents', new Config());

        $fileExists = $adapter->fileExists('path.txt');
        static::assertTrue($fileExists);

        $this->remoteAdapter->delete('path.txt');

        $fileExists = $adapter->fileExists('path.txt');
        static::assertTrue($fileExists);
    }

    /**
     * @test
     */
    public function read_reads_saves_remote_read_and_cache_response(): void
    {
        $adapter = $this->adapter();

        static::assertFalse($adapter->fileExists('path.txt'));

        $this->remoteAdapter->write('path.txt', 'foobar', new Config());

        static::assertFalse($this->localCacheAdapter->fileExists('path.txt'));

        $contents = $adapter->read('path.txt');
        static::assertSame('foobar', $contents);

        static::assertTrue($this->localCacheAdapter->fileExists('path.txt'));

        $this->remoteAdapter->delete('path.txt');

        static::assertTrue($adapter->fileExists('path.txt'));

        $contents = $adapter->read('path.txt');
        static::assertSame('foobar', $contents);
    }

    /**
     * @test
     */
    public function read_stream_reads_saves_remote_read_and_cache_response(): void
    {
        $adapter = $this->adapter();

        static::assertFalse($adapter->fileExists('path.txt'));

        $this->remoteAdapter->write('path.txt', 'foobar', new Config());

        static::assertFalse($this->localCacheAdapter->fileExists('path.txt'));

        $contents = stream_get_contents($adapter->readStream('path.txt'));
        static::assertSame('foobar', $contents);

        static::assertTrue($this->localCacheAdapter->fileExists('path.txt'));

        $this->remoteAdapter->delete('path.txt');

        static::assertTrue($adapter->fileExists('path.txt'));

        $contents = stream_get_contents($adapter->readStream('path.txt'));
        static::assertSame('foobar', $contents);
    }

    /**
     * @test
     */
    public function directory_creation_and_deletion_happen_on_both_places(): void
    {
        $adapter = $this->adapter();

        static::assertEmpty(iterator_to_array($this->localCacheAdapter->listContents('/', true)));
        static::assertEmpty(iterator_to_array($this->remoteAdapter->listContents('/', true)));

        $adapter->createDirectory('foo', new Config());

        static::assertCount(1, iterator_to_array($this->localCacheAdapter->listContents('/', true)));
        static::assertCount(1, iterator_to_array($this->remoteAdapter->listContents('/', true)));

        $adapter->deleteDirectory('foo');

        static::assertEmpty(iterator_to_array($this->localCacheAdapter->listContents('/', true)));
        static::assertEmpty(iterator_to_array($this->remoteAdapter->listContents('/', true)));
    }

    /**
     * @test
     */
    public function list_contents_ignores_local_cache(): void
    {
        $adapter = $this->adapter();

        $this->localCacheAdapter->write('file0.txt', 'xyz', new Config());

        static::assertCount(0, iterator_to_array($adapter->listContents('/', true)));

        $this->remoteAdapter->write('file1.txt', 'foo', new Config());
        $this->remoteAdapter->write('file2.txt', 'bar', new Config());

        static::assertCount(2, iterator_to_array($adapter->listContents('/', true)));
    }

    /**
     * @test
     */
    public function last_modified_call_is_never_proxied_to_let_mtime_be_used_for_cache_usage(): void
    {
        $adapter = $this->adapter();

        $this->localCacheAdapter->write('file.txt', 'xyz', new Config());

        $this->expectException(UnableToRetrieveMetadata::class);

        $adapter->lastModified('file.txt');
    }

    /**
     * @test
     */
    public function on_file_size_calls_cache_replies_first(): void
    {
        $adapter = $this->adapter();

        $this->localCacheAdapter->write('file.txt', 'xyz', new Config());

        static::assertSame(3, $adapter->fileSize('file.txt')->fileSize());
    }

    /**
     * @test
     */
    public function move_acts_on_remote_even_when_local_cache_is_empty(): void
    {
        $adapter = $this->adapter();

        $this->remoteAdapter->write('file.txt', 'xyz', new Config());

        $adapter->move('file.txt', 'file2.txt', new Config());

        static::assertTrue($adapter->fileExists('file2.txt'));
        static::assertFalse($adapter->fileExists('file.txt'));
    }

    /**
     * @test
     */
    public function copy_acts_on_remote_even_when_local_cache_is_empty(): void
    {
        $adapter = $this->adapter();

        $this->remoteAdapter->write('file.txt', 'xyz', new Config());

        $adapter->copy('file.txt', 'file2.txt', new Config());

        static::assertTrue($adapter->fileExists('file2.txt'));
        static::assertTrue($adapter->fileExists('file.txt'));
    }

    /**
     * @test
     */
    public function clear_cache_older_than(): void
    {
        $adapter = $this->adapter();

        $new = new DateTimeImmutable('2021-12-01');
        $old = new DateTimeImmutable('2021-01-01');
        $limit = $new->modify('-1 day');

        $file1Path = 'file1.txt';
        $file2Path = 'subfolder/file2.txt';
        $file3Path = 'subfolder/file3.txt';
        $file4Path = 'file4.txt';

        $adapter->write($file1Path, 'bar', new Config());
        $adapter->write($file2Path, 'foo', new Config());
        $adapter->write($file3Path, 'baz', new Config());
        $adapter->write($file4Path, 'xyz', new Config());

        $adapter->touch($file1Path, $limit);
        $adapter->touch($file2Path, $old);
        $adapter->touch($file3Path, $new);
        $adapter->touch($file4Path, $limit->modify('-1 day'));

        $adapter->clearCacheOlderThan($limit);

        static::assertTrue($adapter->fileExists($file1Path));
        static::assertTrue($adapter->fileExists($file2Path));
        static::assertTrue($adapter->fileExists($file3Path));
        static::assertTrue($adapter->fileExists($file4Path));

        static::assertTrue($this->localCacheAdapter->fileExists($file1Path));
        static::assertFalse($this->localCacheAdapter->fileExists($file2Path));
        static::assertTrue($this->localCacheAdapter->fileExists($file3Path));
        static::assertFalse($this->localCacheAdapter->fileExists($file4Path));
    }

    /**
     * @test
     */
    public function read_refreshes_cache_timestamp(): void
    {
        $adapter = $this->adapter();

        $old = new DateTimeImmutable('2021-01-01');
        $oldPath = 'subfolder/old.txt';

        $adapter->write($oldPath, 'foo', new Config());
        $adapter->touch($oldPath, $old);

        static::assertTrue($adapter->fileExists($oldPath));

        static::assertSame('foo', $adapter->read($oldPath));

        $adapter->clearCacheOlderThan($old->modify('+1 day'));

        static::assertTrue($adapter->fileExists($oldPath));
        static::assertTrue($this->localCacheAdapter->fileExists($oldPath));
    }

    /**
     * @test
     */
    public function read_stream_refreshes_cache_timestamp(): void
    {
        $adapter = $this->adapter();

        $old = new DateTimeImmutable('2021-01-01');
        $oldPath = 'subfolder/old.txt';

        $adapter->write($oldPath, 'foo', new Config());
        $adapter->touch($oldPath, $old);

        static::assertTrue($adapter->fileExists($oldPath));

        static::assertSame('foo', stream_get_contents($adapter->readStream($oldPath)));

        $adapter->clearCacheOlderThan($old->modify('+1 day'));

        static::assertTrue($adapter->fileExists($oldPath));
        static::assertTrue($this->localCacheAdapter->fileExists($oldPath));
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        throw new RuntimeException('Only non-static adapter creation allowed');
    }
}
