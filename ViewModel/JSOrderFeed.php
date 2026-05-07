<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\ViewModel;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order\Item;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Model\Config\Source\AddressFallback;
use TurnTo\SocialCommerce\Logger\Monolog;

class JSOrderFeed implements ArgumentInterface
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
    protected $product;
    /**
     * @var Monolog
     */
    protected $logger;

    /**
     * @param Config $config
     * @param Session $checkoutSession
     * @param Image $imageHelper
     * @param Product $product
     * @param Monolog $logger
     */
    public function __construct(
        Config $config,
        Session $checkoutSession,
        Image   $imageHelper,
        Product $product,
        Monolog $logger
    ) {
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper = $imageHelper;
        $this->product = $product;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getFeedPurchaseOrderData()
    {
        // Get the customer's first and last name from their account if possible
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getEntityId()) {
            $this->logger->error(
                'Cannot render TurnTo order feed data because last real order is missing.',
                [
                    'last_real_order_id' => $this->checkoutSession->getLastRealOrderId(),
                    'quote_id' => $this->checkoutSession->getQuoteId(),
                ]
            );
            return json_encode(
                [
                    'error' => true,
                    'errorCode' => 'order_not_found',
                    'errorMessage' => 'No last real order available for order confirmation page.'
                ],
                JSON_PRETTY_PRINT
            );
        }

        $storeId = $order->getStoreId();
        $firstName = $order->getCustomerFirstname();
        $lastName = $order->getCustomerLastname();

        if (empty($firstName)) {
            // Depending on setting, fallback to Shipping Address name or Billing Address name first
            $fallback = $this->config->getConfigValue(Config::CHECKOUT_CUSTOMER_NAME_FALLBACK, $storeId);

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

            if ($address) {
                $firstName = $address->getFirstname();
                $lastName = $address->getLastname();
            }
        }

        $orderItems = [];

        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }
            $product->setStoreId($storeId);
            $sku = $this->config->getUseChildSku($storeId) ? $item->getSku() : $product->getSku();
            $orderItems[] = [
                'title' => $product->getName(),
                'url' => $product->getProductUrl(),
                'sku' => $this->product->turnToSafeEncoding($sku),
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
