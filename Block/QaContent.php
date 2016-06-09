<?php

namespace TurnTo\SocialCommerce\Block;

class QaContent extends AbstractBlock
{
    protected static $staticCacheTag = 'TURNTO_QA_STATIC_CACHE_TAG';

    protected static $staticCacheKey = 'TURNTO_QA_STATIC_CACHE_KEY';

    // This is supposed to be blank
    protected static $contentType = '';

    /**
     * @return mixed
     */
    public function getSetupType()
    {
        return $this->config->getSetupType();
    }
}
