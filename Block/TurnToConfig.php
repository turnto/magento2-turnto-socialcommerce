<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Block;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;
use TurnTo\SocialCommerce\Helper\Version;

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
     * @var Resolver
     */
    protected $localeResolver;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var Version
     */
    private $versionHelper;


    /**
     * TurnToConfig constructor.
     * @param Template\Context $context
     * @param TurnToConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     * @param array $data
     * @param Data $helper
     */
    public function __construct(
        Template\Context $context,
        TurnToConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        array $data = [],
        Data $helper,
        Version $versionHelper
    )
    {
        // Set the template here so that it's easier to manually create a config block to place anywhere, such as widget
        // Set the template first so that if it's overwritten at the block level we don't force this template
        $this->setTemplate('TurnTo_SocialCommerce::turnto-config.phtml');

        parent::__construct($context, $data);

        $this->configHelper = $configHelper;
        $this->localeResolver = $localeResolver;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->versionHelper = $versionHelper;
    }

    /**
     * Takes an array and converts it to JavasScript output with support for \Zend_Json_Expr
     * @return string
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
        $additionalConfigData['extensionVersion'] = ['magentoVersion'=> $this->versionHelper->getMagentoVersion(), 'turnToCart' => $this->versionHelper->getTurnToVersion()];


        if ($this->configHelper->getQaEnabled()) {
            $additionalConfigData['qa'] = [];
        }
        if ($this->configHelper->getVisualContentGalleryRowWidget()) {
            $product = $this->helper->getProduct();
            if ($product) {
                $skus = [$product->getSku()];
                $additionalConfigData['gallery'] = ['skus' => $skus];
            }
        }
        $configData = array_merge($additionalConfigData, $configData);

        /*
         * Zend_Json::encode is used instead of json_encode because the values of iTeaserFunc and reviewsTeaserFunc
         * have to be a JavaScript object. json_encode has no way to accomplish this. See this stack overflow question
         * for more context http://stackoverflow.com/questions/6169640/php-json-encode-encode-a-function
         */

        return \Zend_Json::encode($configData, true, ['enableJsonExprFinder' => true]);
    }
}
