<?php

namespace TurnTo\SocialCommerce\Block;

class ReviewsContent extends AbstractBlock
{
    protected static $staticCacheTag = 'TURNTO_REVIEWS_STATIC_CACHE_TAG';

    protected static $staticCacheKey = 'TURNTO_REVIEWS_STATIC_CACHE_KEY';
    
    protected static $contentType = 'reviews';

    public function getSetupType()
    {
        return $this->config->getReviewsSetupType();
    }
}
