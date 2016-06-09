<?php

namespace TurnTo\SocialCommerce\Block;

class QaTeaser extends AbstractBlock
{
    protected static $staticCacheTag = 'TURNTO_QA_TEASER_CACHE_TAG';

    protected static $staticCacheKey = 'TURNTO_QA_TEASER_CACHE_KEY';

    // This is supposed to be blank
    protected static $contentType = '';

    public function getSetupType()
    {
        return $this->config->getSetupType();
    }
}