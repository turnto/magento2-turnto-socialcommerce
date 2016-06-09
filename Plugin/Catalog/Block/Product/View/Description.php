<?php

namespace TurnTo\SocialCommerce\Plugin\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\View\Description as OriginalDescription;
use TurnTo\SocialCommerce\Helper\Config;

class Description
{
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
     * Removes the Questions tab from the product details section
     *
     * This plugin will remove the Q&A tab from the product details tabs section if the Enable Q&A config field is set
     * to no or if Enable Social Commerce is set to no. It is done this way rather than on the block definition because
     * you can only have one ifconfig attribute. This was done in a plugin rather than in the template that renders all
     * blocks assigned to the detailed_info group to prevent conflicts with other modules or themes.
     *
     * @param OriginalQuestions $subject
     * @param $result
     */
    public function afterGetGroupChildNames(OriginalDescription $subject, $result)
    {
        if (!$this->config->getQaEnabled() || !$this->config->getIsEnabled()) {
            $result = array_diff($result, array('qa.tab'));
        }
        return $result;
    }
}
