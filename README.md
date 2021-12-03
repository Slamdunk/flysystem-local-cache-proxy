# Slam / flysystem-local-cache-proxy

[![Latest Stable Version](https://img.shields.io/packagist/v/slam/flysystem-local-cache-proxy.svg)](https://packagist.org/packages/slam/flysystem-local-cache-proxy)
[![Downloads](https://img.shields.io/packagist/dt/slam/flysystem-local-cache-proxy.svg)](https://packagist.org/packages/slam/flysystem-local-cache-proxy)
[![Integrate](https://github.com/Slamdunk/flysystem-local-cache-proxy/workflows/Integrate/badge.svg?branch=master)](https://github.com/Slamdunk/flysystem-local-cache-proxy/actions)
[![Code Coverage](https://codecov.io/gh/Slamdunk/flysystem-local-cache-proxy/coverage.svg?branch=master)](https://codecov.io/gh/Slamdunk/flysystem-local-cache-proxy?branch=master)
[![Type Coverage](https://shepherd.dev/github/Slamdunk/flysystem-local-cache-proxy/coverage.svg)](https://shepherd.dev/github/Slamdunk/flysystem-local-cache-proxy)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/Slamdunk/flysystem-local-cache-proxy/master)](https://dashboard.stryker-mutator.io/reports/github.com/Slamdunk/flysystem-local-cache-proxy/master)

Save to local disk a copy of written and read files to speed up next reads.

Keep local disk cache small by clearing unfrequently accessed files.

## Installation

Use composer to install these available packages:

```console
$ composer install slam/flysystem-local-cache-proxy
```

## Usage

```php
use SlamFlysystem\LocalCache\LocalCacheProxyAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

$adapter = new LocalCacheProxyAdapter(
    new AwsS3V3Adapter(/* ... */),
    __DIR__ . '/tmp/flysystem-cache'
);

// The FilesystemOperator
$filesystem = new \League\Flysystem\Filesystem($adapter);

// Upload a file, with stream
$handle = fopen('robots.txt', 'r');
$filesystem->writeStream('robots.txt', $handle);
fclose($handle);

// robots.txt is now present both on Aws and locally

// Read the file: no actual hit on Aws
// Each read/readStream refreshes the cache timestamp
$handle = $filesystem->readStream('robots.txt');
echo stream_get_contents('robots.txt', $handle);
fclose($handle);

// Clear infrequently used files to save disk space
$adapter->clearCacheOlderThan((new DateTime)->modify('-1 week'));

// Manually keep fresh a file you know it gets accessed frequently anyway
$adapter->touch('robots.txt', new DateTime);
```

## What about the other packages?

1. [`league/flysystem-cached-adapter`](https://github.com/thephpleague/flysystem-cached-adapter)
is for Flysystem v1, this package is for Flysystem v2
2. [`lustmored/flysystem-v2-simple-cache-adapter`](https://github.com/Lustmored/flysystem-v2-simple-cache-adapter) 
caches metadatas, this package focuses on caching file contents
