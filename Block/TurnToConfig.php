<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
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
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;
use TurnTo\SocialCommerce\Model\Version;

/**
 * @method void setConfigData(TurnToConfigDataSourceInterface|array $config)
 * @method TurnToConfigDataSourceInterface|array getConfigData()
 */
class TurnToConfig extends Template implements TurnToConfigInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;
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
     * @param Context $context
     * @param TurnToConfigHelper $configHelper
     * @param ResolverInterface $localeResolver
     * @param Data $helper
     * @param Version $version
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        TurnToConfigHelper $configHelper,
        ResolverInterface $localeResolver,
        Data $helper,
        Version $version,
        Json $json,
        array $data = []
    ) {
        // Set the template here so that it's easier to manually create a config block to place anywhere, such as widget
        // Set the template first so that if it's overwritten at the block level we don't force this template
        $this->setTemplate('TurnTo_SocialCommerce::turnto-config.phtml');

        parent::__construct($context, $data);

        $this->configHelper = $configHelper;
        $this->localeResolver = $localeResolver;
        $this->helper = $helper;
        $this->version = $version;
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
        $additionalConfigData['siteKey' ] = $this->configHelper->getSiteKey();
        $additionalConfigData = ['locale' => $this->localeResolver->getLocale()];
        $additionalConfigData['extensionVersion'] = ['magentoVersion'=> $this->version->getMagentoVersion(), 'turnToCart' => $this->version->getModuleVersion()];
        $additionalConfigData['baseUrl'] = $this->_storeManager->getStore()->getBaseUrl();
        $additionalConfigData['sso'] = ['userDataFn' => null];

        if ($this->configHelper->getQaEnabled()) {
            $additionalConfigData['qa'] = [];
        }

        if ($this->configHelper->getCommentsPinboardTeaserEnabled() ) {
            $additionalConfigData['commentsPinboardTeaser'] = [];
        }
        if ($this->configHelper->getVisualContentGalleryRowWidget()) {
            $product = $this->helper->getProduct();
            if ($product) {
                $skus = [$product->getSku()];
                $additionalConfigData['gallery'] = ['skus' => $skus];
            }
        }

        // Remove comment capture if disabled
        if (!$this->configHelper->getCommentsCaptureEnabled()) {
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
}
