<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Export;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface as DirectoryWriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Api\FeedFileClientInterface;

/**
 * Writes TSV feeds to var/tmp so large exports never have to be held in memory, then transmits and removes them.
 */
class TemporaryFeedFile
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var FeedClient
     */
    protected $feedClient;

    /**
     * @param Filesystem $filesystem
     * @param FeedClient $feedClient
     */
    public function __construct(Filesystem $filesystem, FeedClient $feedClient)
    {
        $this->filesystem = $filesystem;
        $this->feedClient = $feedClient;
    }

    /**
     * Write a feed through the given callback and return the absolute path of the finished file
     *
     * The file is removed again if writing fails or produces no data.
     *
     * @param string $filePrefix
     * @param callable $writeRows Receives the open WriteInterface
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function create(string $filePrefix, callable $writeRows): string
    {
        $tmpDir = $this->getTmpDirectory();
        $tmpDir->create();
        $fileName = $filePrefix . uniqid('', true) . '.tsv';

        try {
            $outputFile = $tmpDir->openFile($fileName, 'w+');
            try {
                $writeRows($outputFile);
            } finally {
                $outputFile->close();
            }

            if (empty($tmpDir->stat($fileName)['size'])) {
                throw new LocalizedException(__('Invalid CSV data'));
            }
        } catch (Exception $e) {
            if ($tmpDir->isExist($fileName)) {
                $tmpDir->delete($fileName);
            }
            throw $e;
        }

        return $tmpDir->getAbsolutePath($fileName);
    }

    /**
     * Return the contents of a feed file and remove it
     *
     * @param string $filePath
     * @return string
     * @throws FileSystemException
     */
    public function readAndDelete(string $filePath): string
    {
        try {
            return $this->getTmpDirectory()->getDriver()->fileGetContents($filePath);
        } finally {
            $this->delete($filePath);
        }
    }

    /**
     * Send a feed file to TurnTo and remove it, streaming from disk when the configured client supports it
     *
     * @param string $filePath
     * @param string $feedName
     * @param string $feedStyle
     * @param string $storeCode
     * @return void
     * @throws Exception
     */
    public function transmitAndDelete(string $filePath, string $feedName, string $feedStyle, string $storeCode): void
    {
        try {
            if ($this->feedClient instanceof FeedFileClientInterface) {
                $this->feedClient->transmitFeedFromPath($filePath, $feedName, $feedStyle, $storeCode);
            } else {
                $contents = $this->getTmpDirectory()->getDriver()->fileGetContents($filePath);
                $this->feedClient->transmitFeedFile($contents, $feedName, $feedStyle, $storeCode);
            }
        } finally {
            $this->delete($filePath);
        }
    }

    /**
     * Remove a feed file if it still exists
     *
     * @param string $filePath
     * @return void
     * @throws FileSystemException
     */
    protected function delete(string $filePath): void
    {
        $driver = $this->getTmpDirectory()->getDriver();
        if ($driver->isExists($filePath)) {
            $driver->deleteFile($filePath);
        }
    }

    /**
     * The var/tmp directory
     *
     * @return DirectoryWriteInterface
     * @throws FileSystemException
     */
    protected function getTmpDirectory(): DirectoryWriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
    }
}
