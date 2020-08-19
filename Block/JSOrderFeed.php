<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block;

use TurnTo\SocialCommerce\Helper\Product;

class JSOrderFeed extends \Magento\Framework\View\Element\Template
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
     * @var Product
     */
    protected $productHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \TurnTo\SocialCommerce\Helper\Config             $config
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Catalog\Helper\Image                    $imageHelper
     * @param Product                                          $productHelper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Helper\Image $imageHelper,
        Product $productHelper,
        array $data = []
    )
    {

        parent::__construct($context, $data);

        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper = $imageHelper;
        $this->productHelper = $productHelper;
    }

    /**
     * @return string
     */
    public function getFeedPurchaseOrderData()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $firstName = $order->getCustomerFirstname();
        $lastName = $order->getCustomerLastname();

        if (null === $order->getCustomer()) {
            $address = $order->getShippingAddress();

            if (null === $address) {
                $address = $order->getBillingAddress();
            }

            $firstName = $address->getFirstname();
            $lastName = $address->getLastname();
        }

        $orderItems = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            if ($product === null) {
                continue;
            }

            $sku = $this->config->getUseChildSku() ? $item->getSku() : $product->getSku();

            $orderItems[] = [
                'title' => $product->getName(),
                'url' => $product->getProductUrl(),
                'sku' => $this->productHelper->turnToSafeEncoding($sku),
                'getPrice' => $product->getFinalPrice(),
                'itemImageUrl' => $this->imageHelper->init($product, 'product_small_image')->getUrl()
            ];
        }

        return json_encode(
            [
                'orderId' => $order->getRealOrderId(),
                'email' => $order->getCustomerEmail(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'items' => $orderItems
            ],
            JSON_PRETTY_PRINT
        );
    }

    /**
     * @return array
     */
    public function getOrderItemData()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderItems = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            if ($product === null) {
                continue;
            }

            $sku = $this->config->getUseChildSku() ? $item->getSku() : $product->getSku();

            $orderItems[] = json_encode(
                [
                    'title' => $product->getName(),
                    'url' => $product->getProductUrl(),
                    'sku' => $this->productHelper->turnToSafeEncoding($sku),
                    'getPrice' => $product->getFinalPrice(),
                    'itemImageUrl' => $this->imageHelper->init($product, 'product_small_image')->getUrl()
                ],
                JSON_PRETTY_PRINT
            );
        }

        return $orderItems;
    }
}
