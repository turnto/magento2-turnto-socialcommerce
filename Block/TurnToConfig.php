<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Model\Version;

class TurnToConfig extends Template
{
    /**
     * @var ConfigModel
     */
    protected $config;
    /**
     * @var ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var Version
     */
    protected $version;
    /**
     * @var Json
     */
    protected $json;
    /**
     * @var Product
     */
    protected $productModel;

    /**
     * @param Context $context
     * @param ConfigModel $config
     * @param ResolverInterface $localeResolver
     * @param Data $helper
     * @param Version $version
     * @param Product $productModel
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigModel $config,
        ResolverInterface $localeResolver,
        Data $helper,
        Version $version,
        Product $productModel,
        Json $json,
        array $data = []
    ) {
        // Set the template here so that it's easier to manually create a config block to place anywhere, such as widget
        // Set the template first so that if it's overwritten at the block level we don't force this template
        $this->setTemplate('TurnTo_SocialCommerce::turnto-config.phtml');

        parent::__construct($context, $data);

        $this->config = $config;
        $this->localeResolver = $localeResolver;
        $this->helper = $helper;
        $this->version = $version;
        $this->productModel = $productModel;
        $this->json = $json;
    }

    /**
     * Create turnToConfig json
     * @return string
     * @throws NoSuchEntityException
     */
    public function getJavaScriptConfig()
    {
        $configData = $this->getConfigData();

        if ($configData instanceof TurnToConfigDataSourceInterface) {
            $configData = $configData->getData();
        }

        $additionalConfigData['baseUrl'] = $this->_storeManager->getStore()->getBaseUrl();
        $additionalConfigData['siteKey' ] = $this->config->getSiteKey();
        $additionalConfigData = ['locale' => $this->localeResolver->getLocale()];
        $additionalConfigData['extensionVersion'] = ['magentoVersion'=> $this->version->getMagentoVersion(), 'turnToCart' => $this->version->getModuleVersion()];
        $additionalConfigData['baseUrl'] = $this->_storeManager->getStore()->getBaseUrl();
        $additionalConfigData['sso'] = ['userDataFn' => null];

        if ($this->config->getConfigBool(ConfigModel::QA_ENABLE)) {
            $additionalConfigData['qa'] = [];
        }

        if ($this->config->getConfigBool(ConfigModel::CHECKOUT_ENABLE_COMMENTS_PINBOARD_TEASER)) {
            $additionalConfigData['commentsPinboardTeaser'] = [];
        }
        if ($this->config->getConfigBool(ConfigModel::VISUAL_CONTENT_ENABLE_GALLERY_ROW)) {
            $product = $this->helper->getProduct();
            if ($product) {
                $skus = [$this->productModel->turnToSafeEncoding($product->getSku())];
                $additionalConfigData['gallery'] = ['skus' => $skus];
            }
        }

        // Remove comment capture if disabled
        if (!$this->config->getConfigBool(ConfigModel::CHECKOUT_ENABLE_COMMENTS_CAPTURE)) {
            $additionalConfigData['commentCapture'] = ['suppress' => true];
        }

        return $this->addConfigFunctions($configData, $additionalConfigData);
    }

    /**
     * Add functions for teaser links
     * https://docs.turnto.com/en/speedflex-widget-implementation/event-callbacks.html#installation-14743
     * @param array $configData
     * @param array $additionalConfigData
     * @return string
     */
    public function addConfigFunctions($configData, $additionalConfigData)
    {
        $value = '%teaser%';
        $additionalConfigData['teaser'] = $value;
        $teaser = "{
            \"showReviews\": function(){jQuery('#tab-label-reviews-title').click()},
            \"showQa\": function(){jQuery('#tab-label-turnto_qa-title').click()}
        }";

        $configData = array_merge($additionalConfigData, $configData);
        $json = $this->json->serialize($configData);

        return str_replace('"' . $value . '"', $teaser, $json);
    }

    /**
     * @param $path
     * @return mixed|null
     */
    public function getConfigValue($path)
    {
        return $this->config->getConfigValue($path);
    }

    /**
     * @return string
     */
    public function getSiteKey()
    {
        return $this->config->getSiteKey();
    }
}
