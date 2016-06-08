<?php

namespace TurnTo\SocialCommerce\Plugin\Review\Block\Product;

use Magento\Review\Block\Product\Review as OriginalReview;
use TurnTo\SocialCommerce\Helper\Config;

class Review
{
    protected $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Removes the count of reviews from the reviews tab on the PDP
     *
     * The decision was made to exclude the review count from the tab as that data is no longer being retrieved from
     * the magento database.
     *
     * @param OriginalReview $subject
     * @param $result
     */
    public function afterSetTabTitle(OriginalReview $subject, $result)
    {
        if ($this->config->getReviewsEnabled()) {
            $subject->setTitle(__('Reviews'));
        }
    }
}
?>

