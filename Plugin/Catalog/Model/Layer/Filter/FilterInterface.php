<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/7/16
 * Time: 2:47 PM
 */

namespace TurnTo\SocialCommerce\Plugin\Catalog\Model\Layer\Filter;

class FilterInterface
{
    /**
     * @var null|\TurnTo\SocialCommerce\Helper\Config
     */
    protected $configHelper = null;

    /**
     * FilterInterface constructor.
     *
     * @param \TurnTo\SocialCommerce\Helper\Config $configHelper
     */
    public function __construct(\TurnTo\SocialCommerce\Helper\Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\FilterInterface $subject
     * @param array $result
     * @return array
     */
    public function afterGetItems(\Magento\Catalog\Model\Layer\Filter\FilterInterface $subject, array $result)
    {
        if (
            $subject->getName() == \TurnTo\SocialCommerce\Setup\InstallData::AVERAGE_RATING_ATTRIBUTE_LABEL
            && $this->configHelper->getIsEnabled()
            && $this->configHelper->getReviewsEnabled()
        ) {
            krsort($result);
        }
        return $result;
    }
}
