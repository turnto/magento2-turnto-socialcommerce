<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Block\Adminhtml\Edit;

use TurnTo\SocialCommerce\Helper\Config;

class SaveButton implements \Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface
{

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {

        $this->config = $config;
    }

    /**
     * @return array
     * @codeCoverageIgnore
     */
    public function getButtonData()
    {


        $data = [
            'label' => __('Download'),
            'class' => 'save primary',
            'on_click' => '',
        ];

        if(empty($this->config->getAuthorizationKey()) || empty($this->config->getSiteKey())){
            $data['disabled'] = 'true';
        }
        
        return $data;
    }
}
