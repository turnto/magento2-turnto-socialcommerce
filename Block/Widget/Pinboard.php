<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block\Widget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use TurnTo\SocialCommerce\Block\TurnToConfig;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Model\Data\PinboardConfigFactory;
use TurnTo\SocialCommerce\ViewModel\Widget;

class Pinboard extends Template implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = "TurnTo_SocialCommerce::widget/pinboard.phtml";

    /**
     * @var Config
     */
    protected $config;
    /**
     * @var PinboardConfigFactory
     */
    protected $pinboardConfigFactory;
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var Widget
     */
    protected $viewModel;

    /**
     * @param Config $config
     * @param PinboardConfigFactory $pinboardConfigFactory
     * @param Product $product
     * @param Context $context
     * @param Widget $viewModel
     * @param array $data
     */
    public function __construct(
        Config $config,
        PinboardConfigFactory $pinboardConfigFactory,
        Product $product,
        Context $context,
        Widget $viewModel,
        array $data = []
    ) {
        $this->config = $config;
        $this->pinboardConfigFactory = $pinboardConfigFactory;
        $this->product = $product;
        $this->viewModel = $viewModel;
        parent::__construct($context, $data);
    }

    /**
     * Return an array of product SKUs from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductSkus()
    {
        $productSkus = $this->getData('skus');
        if (!$productSkus) {
            return [];
        }

        return array_map([$this->product, 'turnToSafeEncoding'], array_map('trim', explode(',', $productSkus)));
    }

    /**
     * Return an array of product Brands from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductBrands()
    {
        $productBrands = $this->getData('brands');

        return $productBrands ? array_map('trim', explode(',', $productBrands)) : [];
    }

    /**
     * Return an array of product tags from the pinboard widget configuration.
     *
     * @return array
     */
    public function getProductTags()
    {
        $productTags = $this->getData('tags');

        return $productTags ? array_map('trim', explode(',', $productTags)) : [];
    }

    /**
     * Creates a TurnTo config block and outputs its html content
     *
     * @return string
     */
    public function getTurnToConfigHtml()
    {
        /** @var TurnToConfig $pinboardBlock */
        try {
            $pinboardBlock = $this->getLayout()->createBlock(
                TurnToConfig::class,
                'turnto.config.pinboard',
                [
                    'data' => [
                        'view_model' => $this->getViewModel(),
                    ],
                ]
            );
        } catch (LocalizedException $e) {
            return '';
        }

        $pinboardBlock->setConfigData($this->pinboardConfigFactory->create(['pinboardBlock' => $this]));

        return $pinboardBlock->toHtml();
    }

    /**
     * Returns the page title from the pinboard widget configuration.
     *
     * @return string
     */
    public function getPageTitle()
    {
        return $this->getData('title');
    }

    /**
     * Returns the value of a configuration setting
     *
     * @param string $path
     * @return mixed|null
     */
    public function getConfigValue($path)
    {
        return $this->config->getConfigValue($path);
    }

    /**
     * Returns the view model
     *
     * @return Widget
     */
    public function getViewModel(): Widget
    {
        return $this->viewModel;
    }
}
