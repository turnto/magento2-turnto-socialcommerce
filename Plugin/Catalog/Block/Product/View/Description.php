<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Plugin\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\View\Description as ProductDescription;
use TurnTo\SocialCommerce\Model\Config;

class Description
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Description constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
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
     * @param ProductDescription $subject
     * @param $result
     * @return array
     */
    public function afterGetGroupChildNames(ProductDescription $subject, $result)
    {
        if (!$this->config->getIsEnabled()) {
            $result = array_diff($result, ['turnto.qa.tab']);
        }

        return $result;
    }
}
