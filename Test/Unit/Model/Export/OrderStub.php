<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Order contract extended with getStore(), which Orders::getItemData() relies on but which
 * is declared on the concrete order model rather than the service contract.
 */
interface OrderStub extends OrderInterface
{
    public function getStore();
}
