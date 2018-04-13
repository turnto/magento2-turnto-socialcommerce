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

namespace TurnTo\SocialCommerce\Block\Widget;

// Provide backwards compatibility with Magento < 2.2.x
if (!class_exists('Magento\Framework\Serialize\Serializer\Json')) {
    class Json
    {
    }
} else {
    class_alias(
        'Magento\Framework\Serialize\Serializer\Json',
        'TurnTo\SocialCommerce\Block\Widget\Json'
    );
}

