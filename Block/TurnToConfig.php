<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Block;

use Magento\Framework\View\Element\Template;
use TurnTo\SocialCommerce\Api\TurnToConfigDataProviderInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;

/**
 * @method void setConfigData(TurnToConfigDataProviderInterface $config)
 * @method TurnToConfigDataProviderInterface getConfigData()
 */
class TurnToConfig extends Template implements TurnToConfigInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;

    public function __construct(
        Template\Context $context,
        TurnToConfigHelper $configHelper,
        array $data = []
    )
    {
        // Set the template here so that it's easier to manually create a config block to place anywhere, such as widget
        // Set the template first so that if it's overwritten at the block level we don't force this template
        $this->setTemplate('TurnTo_SocialCommerce::turnto-config.phtml');

        parent::__construct($context, $data);

        $this->configHelper = $configHelper;
    }

    /**
     * Takes an array and converts it to JavasScript output with support for \Zend_Json_Expr
     * @return string
     */
    final public function getJavaScriptConfig(): string
    {
        /*
         * Zend_Json::encode is used instead of json_encode because the values of iTeaserFunc and reviewsTeaserFunc
         * have to be a JavaScript object. json_encode has no way to accomplish this. See this stack overflow question
         * for more context http://stackoverflow.com/questions/6169640/php-json-encode-encode-a-function
         */
        return \Zend_Json::encode($this->getConfigData()->getData(), false, ['enableJsonExprFinder' => true]);
    }

    /**
     * {@inheritdoc}
     */
    final public function getCustomJavaScriptConfiguration(): string
    {
        return $this->configHelper->getCustomConfigurationJs();
    }
}
