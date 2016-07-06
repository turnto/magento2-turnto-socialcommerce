<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/1/16
 * Time: 3:59 PM
 */

namespace TurnTo\SocialCommerce\Controller\Adminhtml\System\HistoricalOrders;


class Index extends \Magento\Backend\App\Action
{
    const INITIATING_MENU = 'TurnTo_SocialCommerce::historical_orders_feed';

    const TITLE = 'Historical Orders Feed';

    protected $coreRegistry = null;

    protected $dateFilter = null;

    protected $title = null;

    /**
     * Index constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\Stdlib\DateTime\Filter\Date $dateFilter
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Stdlib\DateTime\Filter\Date $dateFilter
    ) {
        parent::__construct($context);
        $this->coreRegistry = $coreRegistry;
        $this->dateFilter = $dateFilter;
        $this->title = __(self::TITLE);
    }

    /**
     * Init action
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->_view->loadLayout();
        $this->_setActiveMenu(self::INITIATING_MENU)->_addBreadcrumb($this->title, $this->title);
        $this->_view->getPage()->getConfig()->getTitle()->prepend($this->title);
        return $this;
    }
    
    public function execute()
    {
        $this->_initAction();
        $this->_view->renderLayout();
    }
}
