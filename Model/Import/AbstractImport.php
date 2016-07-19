<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Import;

class AbstractImport
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $config = null;

    /**
     * @var null|\TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger = null;

    /**
     * @var \Magento\Catalog\Model\ProductFactory|null
     */
    protected $productFactory = null;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface|null
     */
    protected $encryptor = null;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Eav\Processor|null
     */
    protected $productEavIndexProcessor = null;

    /**
     * AbstractImport constructor.
     *
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Indexer\Product\Eav\Processor $productEavIndexProcessor
     */
    public function __construct (
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Indexer\Product\Eav\Processor $productEavIndexProcessor
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->productEavIndexProcessor = $productEavIndexProcessor;
    }
}
