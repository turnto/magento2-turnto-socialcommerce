<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

use LogicException;
use Magento\Store\Api\Data\StoreInterface;

interface FeedGeneratorInterface
{
    /**
     * Start the feed generation process.
     *
     * @param StoreInterface $store
     * @return void
     */
    public function beginFeed(StoreInterface $store);

    /**
     * Add a product to the feed.
     *
     * @param mixed $product
     * @param mixed $parent
     * @param int|string|null $storeId
     * @return bool True when line/item was added to the feed, false otherwise.
     */
    public function addProduct($product, $parent = null, $storeId = null): bool;

    /**
     * Finish the feed generation and return the feed data.
     *
     * @return mixed string
     * @throws LogicException If the feed has not been initialized via beginFeed().
     */
    public function finishFeed();

    /**
     * Check whether the feed stream is currently open and accepting new products.
     *
     * @return bool
     */
    public function isFeedOpen(): bool;

    /**
     * Get the feed style identifier.
     *
     * @return string
     */
    public function getFeedStyle(): string;
}
