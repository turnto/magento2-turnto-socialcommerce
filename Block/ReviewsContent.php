<?php

namespace TurnTo\SocialCommerce\Block;

/**
 * {@inheritdoc}
 */
class ReviewsContent extends AbstractBlock
{
    /**
     * {@inheritdoc}
     */
    protected static $staticCacheTag = 'TURNTO_REVIEWS_STATIC_CACHE_TAG';

    /**
     * {@inheritdoc}
     */
    protected static $staticCacheKey = 'TURNTO_REVIEWS_STATIC_CACHE_KEY';

    /**
     * {@inheritdoc}
     */
    protected static $contentType = 'reviews';

    /**
     * @return mixed
     */
    public function getSetupType()
    {
        return $this->config->getReviewsSetupType();
    }
}
