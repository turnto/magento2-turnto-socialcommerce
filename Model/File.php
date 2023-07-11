<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use TurnTo\SocialCommerce\Logger\Monolog;

class File
{
    /**
     * @var WriteInterface
     */
    protected $varDir;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Monolog $logger
     * @param Filesystem $filesystem
     */
    public function __construct(
        Monolog      $logger,
        Filesystem   $filesystem
    ) {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
    }

    /**
     * @param string $path
     * @param string $content
     * @return void
     */
    public function writeFile(string $path, string $content)
    {
        try {
            if (!isset($this->varDir)) {
                $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
                $varDir->create('turnto');
                $this->varDir = $varDir;
            }
            $this->varDir->writeFile($path, $content);
        } catch (FileSystemException $e) {
            $this->logger->error('Unable to write feed file to var/turnto', ['exception' => $e]);
        }
    }
}
