<?php

namespace TurnTo\SocialCommerce\Block;

class ReviewsTeaser extends AbstractBlock
{
    protected static $staticCacheTag = 'TURNTO_REVIEWS_TEASER_CACHE_TAG';

    protected static $staticCacheKey = 'TURNTO_REVIEWS_TEASER_CACHE_KEY';

    protected static $contentType = 'reviews';

    public function getSetupType()
    {
        return $this->config->getReviewsSetupType();
    }
}