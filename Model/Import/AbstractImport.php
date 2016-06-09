<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/7/16
 * Time: 9:33 AM
 */

namespace TurnTo\SocialCommerce\Model\Import;

class AbstractImport
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $config = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    protected $storeManager = null;

    /**
     * @var null
     */
    protected $productCollectionFactory = null;

    /**
     * @var null
     */
    protected $httpClient = null;

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
     * @var null
     */
    protected $productRepository = null;

    /**
     * AbstractImport constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct (
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->encryptor = $encryptor;
    }
}
