<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Sso
{
    public const XML_PATH_SOCIALCOMMERCE_REVIEW_MSG = 'turnto_socialcommerce_configuration/sso/review_msg';

    public const XML_PATH_SOCIALCOMMERCE_REVIEW_MSG_PUR_REQ = 'turnto_socialcommerce_configuration/sso/review_msg_pur_req';

    public const XML_PATH_SOCIALCOMMERCE_QUESTION_MSG = 'turnto_socialcommerce_configuration/sso/question_msg';

    public const XML_PATH_SOCIALCOMMERCE_QUESTION_MSG_ANON = 'turnto_socialcommerce_configuration/sso/question_msg_anon';

    public const XML_PATH_SOCIALCOMMERCE_ANSWER_MSG = 'turnto_socialcommerce_configuration/sso/answer_msg';

    public const XML_PATH_SOCIALCOMMERCE_REPLY_MSG = 'turnto_socialcommerce_configuration/sso/reply_msg';


    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getReviewMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REVIEW_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getReviewMsgPurchaseReq($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REVIEW_MSG_PUR_REQ,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getQuestionMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_QUESTION_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getQuestionMsgAnon($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_QUESTION_MSG_ANON,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getAnswerMessage($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_ANSWER_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * @param int|null|string $store
     * @return string
     */
    public function getReplyMsg($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SOCIALCOMMERCE_REPLY_MSG,
            ScopeInterface::SCOPE_STORE,
            $store ?: $this->getStoreCode()
        );
    }

    /**
     * Gets the store code from the currently set/scoped store
     * @return string|null
     */
    public function getStoreCode()
    {
        try {
            return $this->storeManager->getStore()->getCode();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}

