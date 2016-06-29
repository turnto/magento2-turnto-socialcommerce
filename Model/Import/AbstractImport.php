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
     * AbstractImport constructor.
     * 
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct (
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
    }
}
