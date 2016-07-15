<?php

namespace TurnTo\SocialCommerce\Block;

/**
 * {@inheritdoc}
 */
class QaContent extends AbstractBlock
{
    /**
     * {@inheritdoc}
     */
    protected static $staticCacheTag = 'TURNTO_QA_STATIC_CACHE_TAG';

    /**
     * {@inheritdoc}
     */
    protected static $staticCacheKey = 'TURNTO_QA_STATIC_CACHE_KEY';

    /**
     * {@inheritdoc}
     */
    protected static $contentType = '';

    /**
     * @return mixed
     */
    public function getSetupType()
    {
        return $this->config->getSetupType();
    }
}
