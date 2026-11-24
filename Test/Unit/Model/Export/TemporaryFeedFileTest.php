<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface as DirectoryWriteInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\File\WriteInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Api\FeedFileClientInterface;
use TurnTo\SocialCommerce\Model\Export\TemporaryFeedFile;

class TemporaryFeedFileTest extends TestCase
{
    protected const TMP_PATH = '/var/www/html/var/tmp/';

    /**
     * @var array<string, string> Files in the fake tmp directory, keyed by name
     */
    protected $files = [];
    /**
     * @var string[] Names of files that were deleted
     */
    protected $deleted = [];
    /**
     * @var bool
     */
    protected $fileClosed = false;

    protected function setUp(): void
    {
        $this->files = [];
        $this->deleted = [];
        $this->fileClosed = false;
    }

    public function testCreateWritesThroughTheCallbackAndReturnsTheAbsolutePath(): void
    {
        $path = $this->createTemporaryFeedFile()->create('turnto_test_', function (WriteInterface $file) {
            $file->writeCsv(['ORDERID'], "\t", '"');
        });

        $this->assertMatchesRegularExpression('#^/var/www/html/var/tmp/turnto_test_.+\.tsv$#', $path);
        $this->assertSame("ORDERID\n", $this->files[basename($path)]);
        $this->assertTrue($this->fileClosed);
        $this->assertSame([], $this->deleted);
    }

    public function testCreateRemovesTheFileAndRethrowsWhenWritingFails(): void
    {
        $failure = new Exception('write failure');

        try {
            $this->createTemporaryFeedFile()->create('turnto_test_', function (WriteInterface $file) use ($failure) {
                $file->writeCsv(['ORDERID'], "\t", '"');
                throw $failure;
            });
            $this->fail('The write failure should be rethrown.');
        } catch (Exception $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertTrue($this->fileClosed);
        $this->assertCount(1, $this->deleted);
        $this->assertSame([], $this->files);
    }

    public function testCreateRejectsAnEmptyFeedAndRemovesIt(): void
    {
        try {
            $this->createTemporaryFeedFile()->create('turnto_test_', function () {
            });
            $this->fail('An empty feed should be rejected.');
        } catch (LocalizedException $e) {
            $this->assertSame('Invalid CSV data', $e->getMessage());
        }

        $this->assertCount(1, $this->deleted);
        $this->assertSame([], $this->files);
    }

    public function testReadAndDeleteReturnsTheContentsAndRemovesTheFile(): void
    {
        $this->files['feed.tsv'] = "ORDERID\n";

        $this->assertSame("ORDERID\n", $this->createTemporaryFeedFile()->readAndDelete(self::TMP_PATH . 'feed.tsv'));
        $this->assertSame(['feed.tsv'], $this->deleted);
    }

    public function testTransmitAndDeleteStreamsFromDiskWhenTheClientSupportsIt(): void
    {
        $this->files['feed.tsv'] = "ORDERID\n";
        $feedClient = $this->createMockForIntersectionOfInterfaces([FeedClient::class, FeedFileClientInterface::class]);
        $feedClient->expects($this->once())
            ->method('transmitFeedFromPath')
            ->with(self::TMP_PATH . 'feed.tsv', 'orders.tsv', 'tab-style.1', 'default');
        $feedClient->expects($this->never())->method('transmitFeedFile');

        $this->createTemporaryFeedFile($feedClient)
            ->transmitAndDelete(self::TMP_PATH . 'feed.tsv', 'orders.tsv', 'tab-style.1', 'default');

        $this->assertSame(['feed.tsv'], $this->deleted);
    }

    public function testTransmitAndDeleteSendsTheContentsThroughAPlainFeedClient(): void
    {
        $this->files['feed.tsv'] = "ORDERID\n";
        $feedClient = $this->createMock(FeedClient::class);
        $feedClient->expects($this->once())
            ->method('transmitFeedFile')
            ->with("ORDERID\n", 'orders.tsv', 'tab-style.1', 'default');

        $this->createTemporaryFeedFile($feedClient)
            ->transmitAndDelete(self::TMP_PATH . 'feed.tsv', 'orders.tsv', 'tab-style.1', 'default');

        $this->assertSame(['feed.tsv'], $this->deleted);
    }

    public function testTransmitAndDeleteRemovesTheFileWhenTransmissionFails(): void
    {
        $this->files['feed.tsv'] = "ORDERID\n";
        $failure = new Exception('transmission failure');
        $feedClient = $this->createStub(FeedClient::class);
        $feedClient->method('transmitFeedFile')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        try {
            $this->createTemporaryFeedFile($feedClient)
                ->transmitAndDelete(self::TMP_PATH . 'feed.tsv', 'orders.tsv', 'tab-style.1', 'default');
        } finally {
            $this->assertSame(['feed.tsv'], $this->deleted);
        }
    }

    protected function createTemporaryFeedFile(?FeedClient $feedClient = null): TemporaryFeedFile
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturnCallback(function ($directoryCode) {
            $this->assertSame(DirectoryList::TMP, $directoryCode);

            return $this->createTmpDirectory();
        });

        return new TemporaryFeedFile($filesystem, $feedClient ?? $this->createStub(FeedClient::class));
    }

    /**
     * In-memory stand-in for var/tmp
     *
     * @return DirectoryWriteInterface
     */
    protected function createTmpDirectory(): DirectoryWriteInterface
    {
        $driver = $this->createStub(DriverInterface::class);
        $driver->method('fileGetContents')->willReturnCallback(function ($path) {
            return $this->files[basename($path)];
        });
        $driver->method('isExists')->willReturnCallback(function ($path) {
            return isset($this->files[basename($path)]);
        });
        $driver->method('deleteFile')->willReturnCallback(function ($path) {
            $this->deleteFile(basename($path));

            return true;
        });

        $directory = $this->createStub(DirectoryWriteInterface::class);
        $directory->method('create')->willReturn(true);
        $directory->method('getDriver')->willReturn($driver);
        $directory->method('getAbsolutePath')->willReturnCallback(function ($path = null) {
            return self::TMP_PATH . $path;
        });
        $directory->method('openFile')->willReturnCallback(function ($name) {
            $this->files[$name] = '';

            return $this->createOpenFile($name);
        });
        $directory->method('stat')->willReturnCallback(function ($name) {
            return ['size' => strlen($this->files[$name])];
        });
        $directory->method('isExist')->willReturnCallback(function ($name) {
            return isset($this->files[$name]);
        });
        $directory->method('delete')->willReturnCallback(function ($name) {
            $this->deleteFile($name);

            return true;
        });

        return $directory;
    }

    protected function createOpenFile(string $name): WriteInterface
    {
        $file = $this->createStub(WriteInterface::class);
        $file->method('writeCsv')->willReturnCallback(function (array $row, $delimiter) use ($name) {
            $line = implode($delimiter, $row) . "\n";
            $this->files[$name] .= $line;

            return strlen($line);
        });
        $file->method('close')->willReturnCallback(function () {
            $this->fileClosed = true;

            return true;
        });

        return $file;
    }

    protected function deleteFile(string $name): void
    {
        unset($this->files[$name]);
        $this->deleted[] = $name;
    }
}
