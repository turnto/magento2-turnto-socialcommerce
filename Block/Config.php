<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2017 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block;

use \Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class Config extends \Magento\Catalog\Block\Product\View\Description
{
    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_product;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;

    /**
     * Config constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Framework\Locale\Resolver $localeResolver
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Framework\Locale\Resolver $localeResolver,
        array $data
    ) {
        $this->config = $config;
        $this->localeResolver = $localeResolver;
        parent::__construct($context, $registry, $data);
        $this->_product = $this->getProduct();
    }

    /**
     * Build config json to be assigned to the turnToConfig variable
     *
     * @param bool $skipCssLoad
     * @param bool $loadQA
     * @param bool $loadReviews
     * @param bool $loadCC
     * @param bool $loadSSO
     * @return string
     */
    public function getConfigJson(
        $skipCssLoad = true,
        $loadQA = true,
        $loadReviews = true,
        $loadCC = true,
        $loadSSO = true
    ){
        $config = [
            'siteKey' => $this->config->getSiteKey(),
            'host' => $this->config->getUrlWithoutProtocol(),
            'staticHost' => $this->config->getStaticUrlWithoutProtocol(),
            'skipCssLoad' => $skipCssLoad,
        ];

        if ($this->config->getQaEnabled() && $loadQA) {
            $config['setupType'] = $this->config->getSetupType();
            if ($this->config->getQaTeaserEnabled()) {
                $config['iTeaserFunc'] = new \Zend_Json_Expr('qaTeaser');
            }
        }

        if ($this->config->getReviewsEnabled() && $loadReviews) {
            $config['reviewsSetupType'] = $this->config->getReviewsSetupType();
            if ($this->config->getReviewsTeaserEnabled()) {
                $config['reviewsTeaserFunc'] = new \Zend_Json_Expr('reviewsTeaser');
            }
        }

        if ($this->config->getCheckoutCommentsEnabledProductDetail() && $loadCC) {
            $config['chatter'] = [
                'minimumCommentCount' => 1,
                'minimumCommentCharacterCount' => 1,
                'minimumCommentWordCount' => 1,
                'columns' => $this->config->getColumns()
            ];
        }

        if ($this->config->getSingleSignOn() && $loadSSO) {
            $config['registration'] = [
                'localGetLoginStatusFunction' => new \Zend_Json_Expr('localGetLoginStatusFunction'),
                'localRegistrationUrl' => $this->getBaseUrl() . 'turnto/sso/login',
                'localGetUserInfoFunction' => new \Zend_Json_Expr('localGetUserInfoFunction'),
                'localLogoutFunction' => new \Zend_Json_Expr('localLogoutFunction')
            ];
        }

        if (is_array($this->config->getCustomConfigurationJs())) {
            $config = array_merge($config, $this->config->getCustomConfigurationJs());
        }

        /*
         * Zend_Json::encode is used instead of json_encode because the values of iTeaserFunc and reviewsTeaserFunc
         * have to be a JavaScript object. json_encode has no way to accomplish this. See this stack overflow question
         * for more context http://stackoverflow.com/questions/6169640/php-json-encode-encode-a-function
         */
        return \Zend_Json::encode($config, false, ['enableJsonExprFinder' => true]);
    }

    /**
     * @return null|string
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * @return string
     */
    public function getProductSku()
    {
        $product = $this->_product;

        if ($this->config->getUseChildSku() && $product->getTypeId() == Configurable::TYPE_CODE) {
            return array_values($product->getTypeInstance()->getUsedProducts($product))[0]->getSku();
        }

        return $product->getSku();
    }

    /**
     * @return string
     */
    public function getGallerySkus()
    {
        $product = $this->_product;
        $gallerySkus = [];

        if ($this->config->getUseChildSku() && $product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            if (count($children) > 0) {
                foreach ($children as $child) {
                    $gallerySkus[] = $child->getSku();
                }
            }
        } else {
            $gallerySkus[] = $product->getSku();
        }

        return json_encode($gallerySkus);
    }
}
