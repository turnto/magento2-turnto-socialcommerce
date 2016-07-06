<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/6/16
 * Time: 2:20 PM
 */

namespace TurnTo\SocialCommerce\Block\Adminhtml\Edit;


class SaveButton extends GenericButton implements \Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface
{
    /**
     * @return array
     * @codeCoverageIgnore
     */
    public function getButtonData()
    {
        $data = [];
        if ($this->canRender('save')) {
            $data = [
                'label' => __('Download'),
                'class' => 'save primary',
                'on_click' => '',
            ];
        }
        return $data;
    }
}
