<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TurnTo\SocialCommerce\Block;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\ScopeInterface;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Helper\Product;
use TurnTo\SocialCommerce\Model\Config\Checkout as CheckoutConfig;
use TurnTo\SocialCommerce\Model\Config\General as GeneralConfig;
use TurnTo\SocialCommerce\Model\Config\Source\AddressFallback;

class JSOrderFeed extends Template
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var Product
     */
    protected $productHelper;
    /**
     * @var CheckoutConfig
     */
    protected $checkoutConfig;
    /**
     * @var GeneralConfig
     */
    protected $generalConfig;

    /**
     * @param Context $context
     * @param CheckoutConfig $checkoutConfig
     * @param GeneralConfig $generalConfig
     * @param Session $checkoutSession
     * @param Image $imageHelper
     * @param Product $productHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CheckoutConfig $checkoutConfig,
        GeneralConfig $generalConfig,
        Session $checkoutSession,
        Image   $imageHelper,
        Product $productHelper,
        array   $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutConfig = $checkoutConfig;
        $this->generalConfig = $generalConfig;
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper = $imageHelper;
        $this->productHelper = $productHelper;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getFeedPurchaseOrderData()
    {
        // Get the customer's first and last name from their account if possible
        $order = $this->checkoutSession->getLastRealOrder();
        $storeId = $order->getStoreId();
        $firstName = $order->getCustomerFirstname();
        $lastName = $order->getCustomerLastname();

        if (empty($firstName)) {
            // Depending on setting, fallback to Shipping Address name or Billing Address name first
            $fallback = $this->checkoutConfig->getJSOrderFeedCustomerNameFallback(ScopeInterface::SCOPE_STORE, $storeId);

            if ($fallback === AddressFallback::BILLING_ADDRESS_VALUE) {
                $address = $order->getBillingAddress();
                if (null === $address) {
                    $address = $order->getShippingAddress();
                }
            } else {
                $address = $order->getShippingAddress();
                if (null === $address) {
                    $address = $order->getBillingAddress();
                }
            }

            $firstName = $address->getFirstname();
            $lastName = $address->getLastname();
        }

        $orderItems = [];

        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }
            $product->setStoreId($storeId);
            $sku = $this->generalConfig->getUseChildSku(ScopeInterface::SCOPE_STORE, $storeId) ? $item->getSku() : $product->getSku();
            $orderItems[] = [
                'title' => $product->getName(),
                'url' => $product->getProductUrl(),
                'sku' => $this->productHelper->turnToSafeEncoding($sku),
                'itemImageUrl' => $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                'price' => $item->getPrice(),
                'qty' => (int)$item->getQtyOrdered()
            ];
        }

        return json_encode(
            [
                'orderId' => $order->getRealOrderId(),
                'email' => $order->getCustomerEmail(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'total' => (float)$order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
                'items' => $orderItems
            ],
            JSON_PRETTY_PRINT
        );
    }
}
