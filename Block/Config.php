<?php

namespace TurnTo\SocialCommerce\Block;

class Config extends \Magento\Catalog\Block\Product\View\Description
{
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
    }

    /**
     * Build config json to be assigned to the turnToConfig variable
     *
     * @return string
     */
    public function getConfigJson()
    {
        $config = [
            'siteKey' => $this->config->getSiteKey(),
            'host' => $this->config->getUrlWithoutProtocol(),
            'staticHost' => $this->config->getStaticUrlWithoutProtocol(),
            'skipCssLoad' => true,
        ];

        if ($this->config->getQaEnabled()) {
            $config['setupType'] = $this->config->getSetupType();
            if ($this->config->getQaTeaserEnabled()) {
                $config['iTeaserFunc'] = new \Zend_Json_Expr('qaTeaser');
            }
        }

        if ($this->config->getReviewsEnabled()) {
            $config['reviewsSetupType'] = $this->config->getReviewsSetupType();
            if ($this->config->getReviewsTeaserEnabled()) {
                $config['reviewsTeaserFunc'] = new \Zend_Json_Expr('reviewsTeaser');
            }
        }

        if ($this->config->getCheckoutCommentsEnabled()) {
            $config['chatter'] = [
                'minimumCommentCount' => 1,
                'minimumCommentCharacterCount' => 1,
                'minimumCommentWordCount' => 1
            ];
        }

        /*
         * Zend_Json::encode is used instead of json_encode because the values of iTeaserFunc and reviewsTeaserFunc
         * have to be a JavaScript object. json_encode has no way to accomplish this. See this stack overflow question
         * for more context http://stackoverflow.com/questions/6169640/php-json-encode-encode-a-function
         */
        return \Zend_Json::encode($config, false, array('enableJsonExprFinder' => true));
    }

    public function getLocale() {
        return $this->localeResolver->getLocale();
    }
}
