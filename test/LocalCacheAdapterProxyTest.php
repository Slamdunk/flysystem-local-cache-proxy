<?php

declare(strict_types=1);

namespace SlamFlysystem\LocalCache\Test;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\MimeTypeDetection\EmptyExtensionToMimeTypeMap;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use RuntimeException;
use SlamFlysystem\LocalCache\LocalCacheProxyAdapter;

/**
 * @covers \SlamFlysystem\LocalCache\LocalCacheProxyAdapter
 *
 * @internal
 */
final class LocalCacheAdapterProxyTest extends FilesystemAdapterTestCase
{
    protected ?FilesystemAdapter $customAdapter = null;
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

    public function adapter(): FilesystemAdapter
    {
        if (null === $this->customAdapter) {
            $this->customAdapter = new LocalCacheProxyAdapter(
                $this->remoteAdapter = new LocalFilesystemAdapter($this->remoteRoot),
                $this->localCacheAdapter = new LocalFilesystemAdapter($this->localRoot)
            );
        }

        return $this->customAdapter;
    }

    /**
     * Delete this test once https://github.com/thephpleague/flysystem/pull/1375 is released.
     *
     * @test
     */
    public function writing_a_file_with_a_stream(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $writeStream = stream_with_contents('contents');
            self::assertIsResource($writeStream);

            $adapter->writeStream('path.txt', $writeStream, new Config());
            fclose($writeStream);
            $fileExists = $adapter->fileExists('path.txt');

            $this->assertTrue($fileExists);
        });
    }

    /**
     * Delete this test once https://github.com/thephpleague/flysystem/pull/1375 is released.
     *
     * @test
     */
    public function writing_a_file_with_an_empty_stream(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $writeStream = stream_with_contents('');
            self::assertIsResource($writeStream);

            $adapter->writeStream('path.txt', $writeStream, new Config());
            fclose($writeStream);
            $fileExists = $adapter->fileExists('path.txt');

            $this->assertTrue($fileExists);

            $contents = $adapter->read('path.txt');
            $this->assertSame('', $contents);
        });
    }

    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->customAdapter = new LocalCacheProxyAdapter(
            new LocalFilesystemAdapter($this->remoteRoot, null, LOCK_EX, LocalFilesystemAdapter::DISALLOW_LINKS, new ExtensionMimeTypeDetector(new EmptyExtensionToMimeTypeMap())),
            new LocalFilesystemAdapter($this->localRoot)
        );

        parent::fetching_unknown_mime_type_of_a_file();
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        throw new RuntimeException('Only non-static adapter creation allowed');
    }
}
