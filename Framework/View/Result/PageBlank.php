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

namespace TurnTo\SocialCommerce\Framework\View\Result;

/**
 * This result class is similar to \Magento\Framework\Controller\Result\Raw, but it will render the layout
 */
class PageBlank extends \Magento\Framework\View\Result\Page
{
    const TYPE_PAGE_BLANK = 'page_blank';
}
