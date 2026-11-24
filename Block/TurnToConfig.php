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
     * Create turnToConfig json including the teaser callback functions
     *
     * @return string
     * @throws NoSuchEntityException
     * @deprecated The default template adds the teaser callbacks itself; use getConfigJson() and getTeaserSelectors()
     * @see getConfigJson()
     */
    public function getJavaScriptConfig()
    {
        return $this->addConfigFunctions($this->getConfigDataSource(), $this->getAdditionalConfigData());
    }

    /**
     * The turnToConfig object as plain JSON
     *
     * Configuration data set on the block takes precedence over the generated values.
     * The teaser callbacks are added in the template when no "teaser" key is configured.
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getConfigJson(): string
    {
        return $this->json->serialize(
            array_merge($this->getAdditionalConfigData(), (array) $this->getConfigDataSource())
        );
    }

    /**
     * Selectors for the tabs opened by the teaser "showReviews" and "showQa" callbacks
     *
     * @return array{reviews: string, qa: string}
     */
    public function getTeaserSelectors(): array
    {
        return [
            'reviews' => (string) ($this->getData('teaser_reviews_tab_selector') ?: '#tab-label-reviews-title'),
            'qa' => (string) ($this->getData('teaser_qa_tab_selector') ?: '#tab-label-turnto_qa-title'),
        ];
    }

    /**
     * Teaser tab selectors as a JSON object
     *
     * @return string
     */
    public function getTeaserSelectorsJson(): string
    {
        return $this->json->serialize($this->getTeaserSelectors());
    }

    /**
     * Configuration data provided through the block's config_data argument
     *
     * @return array|mixed
     */
    protected function getConfigDataSource()
    {
        $configData = $this->getConfigData();

        if ($configData instanceof TurnToConfigDataSourceInterface) {
            $configData = $configData->getData();
        }

        return $configData ?? [];
    }

    /**
     * Generated turnToConfig values
     *
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getAdditionalConfigData(): array
    {
        $additionalConfigData = [];
        $additionalConfigData['locale'] = $this->localeResolver->getLocale();
        $additionalConfigData['extensionVersion'] = ['magentoVersion'=> $this->version->getMagentoVersion(), 'turnToCart' => $this->version->getModuleVersion()];
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

        $widgetBaseUrl = $this->config->getConfigValue(ConfigModel::PRODUCT_WIDGET_URL);
        if (!empty($widgetBaseUrl)) {
            $additionalConfigData['baseUrl'] = rtrim((string) $widgetBaseUrl, '/');
        }
        $siteKey = $this->config->getSiteKey();
        if (!empty($siteKey)) {
            $additionalConfigData['siteKey'] = (string) $siteKey;
        }

        return $additionalConfigData;
    }

    /**
     * Add functions for teaser links
     * https://docs.turnto.com/en/speedflex-widget-implementation/event-callbacks.html#installation-14743
     * @param array $configData
     * @param array $additionalConfigData
     * @return string
     * @deprecated The teaser callbacks are defined in TurnTo_SocialCommerce::turnto-config.phtml
     * @see getConfigJson()
     */
    public function addConfigFunctions($configData, $additionalConfigData)
    {
        $value = '%teaser%';
        $additionalConfigData['teaser'] = $value;
        $selectors = $this->getTeaserSelectors();
        $reviewsTabJson = $this->json->serialize($selectors['reviews']);
        $qaTabJson = $this->json->serialize($selectors['qa']);
        $teaser = '{' . "\"showReviews\": function(){jQuery({$reviewsTabJson}).click()}," . "\"showQa\": function(){jQuery({$qaTabJson}).click()}" . '}';

        $configData = array_merge($additionalConfigData, $configData);
        $json = $this->json->serialize($configData);

        return str_replace('"' . $value . '"', $teaser, $json);
    }

    /**
     * @param $path
     * @return mixed|null
     * @deprecated Use ViewModel
     */
    public function getConfigValue($path)
    {
        return $this->config->getConfigValue($path);
    }

    /**
     * @return string
     * @deprecated Use ViewModel
     */
    public function getSiteKey()
    {
        return $this->config->getSiteKey();
    }
}
