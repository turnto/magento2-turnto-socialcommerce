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
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block;

class CheckoutComments extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * CheckoutComments constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Helper\Image $imageHelper,
        array $data = []
    ) {
    
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper = $imageHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getFeedPurchaseOrderData()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        return json_encode([
            'orderId' => $order->getRealOrderId(),
            'email' => $order->getCustomerEmail(),
            'firstName' => $order->getCustomerFirstname(),
            'lastName' => $order->getCustomerLastname()
        ], JSON_PRETTY_PRINT);
    }

    /**
     * @return array
     */
    public function getOrderItemData()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderItems = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $product = $this->config->getUseChildSku() ? $this->getChildProduct($item) : $item->getProduct();
            $orderItems[] = json_encode([
                'title' => $product->getName(),
                'url' => $product->getProductUrl(),
                'sku' => $product->getSku(),
                'getPrice' => $product->getFinalPrice(),
                'itemImageUrl' => $this->imageHelper->init($product, 'product_small_image')->getUrl()
            ], JSON_PRETTY_PRINT);
        }

        return $orderItems;
    }

    /**
     * @param $item
     * @return mixed
     */
    public function getChildProduct($item)
    {
        $product = $item->getProduct();
        $childSku = $item->getSku();
        $childProducts = $product->getTypeInstance()->getUsedProducts($product);

        foreach ($childProducts as $childProduct) {
            if ($childProduct->getSku() == $childSku) {
                return $childProduct;
            }
        }

        return $product;
    }
}
