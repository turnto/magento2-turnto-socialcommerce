<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block;

use Magento\Framework\View\Element\Template;

abstract class AbstractEmbedBlock extends Template
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \TurnTo\SocialCommerce\Model\Embed\HttpClient
     */
    protected $httpClient;

    /**
     * @var \TurnTo\SocialCommerce\Helper\Product
     */
    protected $turnToProductHelper;

    /**
     * @var string
     */
    protected $contentType;

    /**
     * @var \Magento\Catalog\Block\Product\View
     */
    protected $productViewBlock;

    /**
     * @param Template\Context                              $context
     * @param \Magento\Catalog\Block\Product\View           $productViewBlock
     * @param \TurnTo\SocialCommerce\Helper\Config          $config
     * @param \TurnTo\SocialCommerce\Model\Embed\HttpClient $httpClient
     * @param \TurnTo\SocialCommerce\Helper\Product         $turnToProductHelper
     * @param string                                        $contentType
     * @param array                                         $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Catalog\Block\Product\View $productViewBlock,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \TurnTo\SocialCommerce\Model\Embed\HttpClient $httpClient,
        \TurnTo\SocialCommerce\Helper\Product $turnToProductHelper,
        $contentType = '',
        array $data = []
    )
    {
        parent::__construct($context, $data);

        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->turnToProductHelper = $turnToProductHelper;
        $this->contentType = $contentType;
        $this->productViewBlock = $productViewBlock;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function _toHtml()
    {
        // If a template is provided, we will rely on that template to generate HTML
        if ($this->getTemplate()) {
            return parent::_toHtml();
        }

        $content = '';

        switch ($this->getSetupType()) {
            case \TurnTo\SocialCommerce\Helper\Config::SETUP_TYPE_DYNAMIC_EMBED:
                $content = $this->getDynamicEmbedContent();
                break;
            case \TurnTo\SocialCommerce\Helper\Config::SETUP_TYPE_STATIC_EMBED:
                $content = $this->getStaticEmbedContent();
                break;
        }

        return $content;
    }

    /**
     * @return string
     */
    abstract public function getSetupType();

    /**
     * @return string
     */
    public function getDynamicEmbedContent()
    {
        return '<div id="TurnTo' . ucfirst($this->contentType) . 'Content"></div>';
    }

    /**
     * @return string
     */
    public function getStaticEmbedContent()
    {
        $encodedSku = $this->turnToProductHelper->turnToSafeEncoding($this->productViewBlock->getProduct()->getSku());

        $url = sprintf(
            '%s/sitedata/%s/v%s/%s/d/catitem%shtml',
            $this->config->getStaticUrl(),
            $this->config->getSiteKey(),
            $this->config->getTurnToVersion(),
            urlencode($encodedSku),
            $this->contentType
        );

        return $this->httpClient->getTurnToHtml($url);
    }
}
