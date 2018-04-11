<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Model\Data;

use Magento\Framework\View\Element\Block\ArgumentInterface as BlockArgumentInterface;
use TurnTo\SocialCommerce\Api\TurnToConfigDataProviderInterface;
use TurnTo\SocialCommerce\Helper\Config as TurnToConfigHelper;
use TurnTo\SocialCommerce\Helper\ConfigProviderHelper;

class ProductDetailConfig implements TurnToConfigDataProviderInterface, BlockArgumentInterface
{
    /**
     * @var TurnToConfigHelper
     */
    protected $configHelper;

    /**
     * @var ConfigProviderHelper
     */
    protected $configProviderHelper;

    /**
     * @param TurnToConfigHelper   $configHelper
     * @param ConfigProviderHelper $configProviderHelper
     */
    public function __construct(TurnToConfigHelper $configHelper, ConfigProviderHelper $configProviderHelper)
    {
        $this->configHelper = $configHelper;
        $this->configProviderHelper = $configProviderHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        $config = [
            'siteKey' => $this->configHelper->getSiteKey(),
            'host' => $this->configHelper->getUrlWithoutProtocol(),
            'staticHost' => $this->configHelper->getStaticUrlWithoutProtocol(),
            'skipCssLoad' => true,
        ];

        if ($this->configHelper->getQaEnabled()) {
            $config['setupType'] = $this->configHelper->getSetupType();
            if ($this->configHelper->getQaTeaserEnabled()) {
                $config['iTeaserFunc'] = new \Zend_Json_Expr('qaTeaser');
            }
        }

        if ($this->configHelper->getReviewsEnabled()) {
            $config['reviewsSetupType'] = $this->configHelper->getReviewsSetupType();
            if ($this->configHelper->getReviewsTeaserEnabled()) {
                $config['reviewsTeaserFunc'] = new \Zend_Json_Expr('reviewsTeaser');
            }
        }

        if ($this->configHelper->getCheckoutCommentsEnabledProductDetail()) {
            $config['chatter'] = [
                'minimumCommentCount' => 1,
                'minimumCommentCharacterCount' => 1,
                'minimumCommentWordCount' => 1,
                'columns' => $this->configHelper->getColumns()
            ];
        }

        $config = array_merge($config, $this->configProviderHelper->getSingleSignOnConfig());

        return $config;
    }
}
