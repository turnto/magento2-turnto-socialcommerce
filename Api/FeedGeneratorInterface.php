<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Api;

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
     * @return void
     */
    public function addProduct($product, $parent = null, $storeId = null);

    /**
     * Finish the feed generation and return the feed data.
     *
     * @return mixed string
     */
    public function finishFeed();

    /**
     * Get the feed style identifier.
     *
     * @return string
     */
    public function getFeedStyle(): string;
}
