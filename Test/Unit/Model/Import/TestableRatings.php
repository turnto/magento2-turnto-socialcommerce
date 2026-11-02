<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Import;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Store\Api\Data\StoreInterface;
use TurnTo\SocialCommerce\Model\Import\Ratings;

/**
 * Testable subclass that stubs the external feed download and exposes protected helpers
 * so the bulk-update behavior can be exercised without a network call or database.
 */
class TestableRatings extends Ratings
{
    /**
     * @var string
     */
    public $feedBody = '';

    /**
     * @var array
     */
    public $productsBySku = [];

    /**
     * @var GuzzleException|null
     */
    public $feedException = null;

    public function setFeedBody(string $xml): void
    {
        $this->feedBody = $xml;
    }

    public function setProductsBySku(array $productsBySku): void
    {
        $this->productsBySku = $productsBySku;
    }

    public function setFeedException(GuzzleException $exception): void
    {
        $this->feedException = $exception;
    }

    /**
     * @inheritdoc
     */
    protected function fetchAggregateRatingsFeedBody(string $feedAddress): string
    {
        if ($this->feedException) {
            throw $this->feedException;
        }

        return $this->feedBody;
    }

    public function callRealFetchAggregateRatingsFeedBody(string $feedAddress): string
    {
        return parent::fetchAggregateRatingsFeedBody($feedAddress);
    }

    /**
     * @inheritdoc
     */
    protected function getProductsBySkus(StoreInterface $store, array $skus): array
    {
        return $this->productsBySku;
    }

    public function callResetProducts(array $feedProducts, StoreInterface $store): void
    {
        $this->resetProducts($feedProducts, $store);
    }
}
