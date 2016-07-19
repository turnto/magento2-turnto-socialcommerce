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

namespace TurnTo\SocialCommerce\Plugin\Catalog\Block\Product\View;

class Description
{
    /**
     * @var \TurnTo\SocialCommerce\Helper\Config
     */
    protected $config;

    /**
     * Description constructor.
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Removes the Questions tab from the product details section and/or removes the original Reviews tab
     *
     * This plugin will remove the Q&A or Reviews tab from the product details tabs section if their corresponding
     * enabling config field is set to no or if Enable Social Commerce is set to no. It is done this way rather than on
     * the block definition because you can only have one ifconfig attribute. This was done in a plugin rather than in
     * the template that renders all blocks assigned to the detailed_info group to prevent conflicts with other modules
     * or themes.
     *
     * @param \Magento\Catalog\Block\Product\View\Description $subject
     * @param $result
     */
    public function afterGetGroupChildNames(\Magento\Catalog\Block\Product\View\Description $subject, $result)
    {
        if (!$this->config->getQaEnabled() || !$this->config->getIsEnabled()) {
            $result = array_diff($result, ['turnto.qa.tab']);
        }
        if (!$this->config->getReviewsEnabled() || !$this->config->getIsEnabled()) {
            $result = array_diff($result, ['turnto.reviews.tab']);
        } else {
            $result = array_diff($result, ['reviews.tab']);
        }
        return $result;
    }
}
