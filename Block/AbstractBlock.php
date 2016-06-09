<?php

namespace TurnTo\SocialCommerce\Block;

use TurnTo\SocialCommerce\Helper\Config;

abstract class AbstractBlock extends \Magento\Catalog\Block\Product\View
{
    protected static $staticCacheTag = null;

    protected static $staticCacheKey = null;

    protected static $contentType = null;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Zend\Http\Client
     */
    protected $httpClient;

    /**
     * AbstractBlock constructor.
     *
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Framework\Url\EncoderInterface $urlEncoder
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param \Magento\Customer\Model\Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Zend\Http\Client $httpClient
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Url\EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        Config $config,
        \Zend\Http\Client $httpClient,
        array $data = []
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;

        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );

        $this->addData($this->getCacheData());
    }

    protected function getCacheData()
    {
        $cacheKey = static::$staticCacheKey
            . $this->getProduct()->getId()
            . md5(
                static::getSetupType()
                . $this->config->getTurnToVersion()
                . $this->_storeManager->getStore()->getId()
                . $this->config->getSiteKey()
                . static::$contentType
            );

        return [
            'cache_lifetime' => $this->config->getStaticContentCacheTime(),
            //TODO: Add package and theme to cache_tags
            'cache_tags' => [
                static::$staticCacheTag,
                \Magento\Catalog\Model\Product::CACHE_TAG . '_' . $this->getProduct()->getId(),
            ],
            'cache_key' => $cacheKey
        ];
    }

    public function getContentHtml()
    {
        $setupType = static::getSetupType();
        $staticUrl = $this->config->getStaticUrl();
        $siteKey = $this->config->getSiteKey();
        $version = $this->config->getTurnToVersion();

        if ($setupType == Config::SETUP_TYPE_DYNAMIC_EMBED) {
            return '<div id="TurnTo' . ucfirst(static::$contentType) . 'Content"></div>';
        } else if ($setupType == Config::SETUP_TYPE_STATIC_EMBED) {
            $sku = $this->getProduct()->getSku();
            $url = $staticUrl . '/sitedata/' . $siteKey . '/v' . $version . '/' . $sku . '/d/catitem' . static::$contentType . 'html';
            //TODO: return the file loaded from the model
            return $this->httpContent($url);
        }
        return '';
    }

    public function httpContent($url)
    {
        try{
            $response = null;
            $this->httpClient
                ->setUri($url)
                ->setMethod(\Zend_Http_Client::GET);

            $response = $this->httpClient->send();

            if (!$response || !$response->isSuccess()) {
                throw new \Exception('TurnTo catalog feed submission failed silently');
            }

            $body = $response->getBody();

            return $body;
        } catch (\Exception $e) {
//            $this->logger->error('An error occurred while transmitting the catalog feed to TurnTo',
//                [
//                    'exception' => $e,
//                    'response' => $response ? 'null' : $response->getBody()
//                ]
//            );
            throw $e;
        }
    }

    abstract public function getSetupType();
}
