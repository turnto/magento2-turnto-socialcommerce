<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use TurnTo\SocialCommerce\Model\Config;

class SaveButton implements ButtonProviderInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getButtonData()
    {
        $data = [
            'label' => __('Export'),
            'class' => 'save primary',
            'on_click' => '',
        ];

        if (empty($this->config->getAuthorizationKey()) || empty($this->config->getSiteKey())) {
            $data['disabled'] = 'true';
        }

        return $data;
    }
}
