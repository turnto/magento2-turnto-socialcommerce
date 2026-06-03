<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

/**
 * Minimal contract for the order-item methods consumed by Orders::getItemData().
 *
 * Mocking this stub instead of the concrete Magento order item keeps the unit test
 * independent of the heavyweight sales model.
 */
interface OrderItemStub
{
    public function isDeleted();

    public function getParentItemId();

    public function getItemId();

    public function getProductId();
}
