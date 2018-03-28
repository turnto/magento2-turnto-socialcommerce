<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block;

abstract class AbstractBlock extends \Magento\Catalog\Block\Product\View
{
    /**
     * @var null
     */
    protected static $contentType = null;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \TurnTo\SocialCommerce\Model\Embed\HttpClient
     */
    protected $httpClient;

    /**
     * @param \Magento\Catalog\Block\Product\Context              $context
     * @param \Magento\Framework\Url\EncoderInterface             $urlEncoder
     * @param \Magento\Framework\Json\EncoderInterface            $jsonEncoder
     * @param \Magento\Framework\Stdlib\StringUtils               $string
     * @param \Magento\Catalog\Helper\Product                     $productHelper
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     * @param \Magento\Framework\Locale\FormatInterface           $localeFormat
     * @param \Magento\Customer\Model\Session                     $customerSession
     * @param \Magento\Catalog\Api\ProductRepositoryInterface     $productRepository
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface   $priceCurrency
     * @param \TurnTo\SocialCommerce\Helper\Config                $config
     * @param \TurnTo\SocialCommerce\Model\Embed\HttpClient       $httpClient
     * @param array                                               $data
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
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Model\Embed\HttpClient $httpClient,
        array $data = []
    )
    {
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
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getContentHtml()
    {
        $setupType = static::getSetupType();
        $staticUrl = $this->config->getStaticUrl();
        $siteKey = $this->config->getSiteKey();
        $version = $this->config->getTurnToVersion();

        if ($setupType == \TurnTo\SocialCommerce\Helper\Config::SETUP_TYPE_DYNAMIC_EMBED) {
            return '<div id="TurnTo' . ucfirst(static::$contentType) . 'Content"></div>';
        }

        if ($setupType == \TurnTo\SocialCommerce\Helper\Config::SETUP_TYPE_STATIC_EMBED) {
            $url = sprintf(
                '%s/sitedata/%s/v%s/%s/d/catitem%shtml',
                $staticUrl,
                $siteKey,
                $version,
                urlencode($this->getProduct()->getSku()),
                static::$contentType
            );

            return $this->httpClient->getTurnToHtml($url);
        }

        return '';
    }

    /**
     * @return mixed
     */
    abstract public function getSetupType();
}
