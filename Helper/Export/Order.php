<?php
/**
 * @category    ClassyLlama
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 * @author      sean.templeton
 */

namespace TurnTo\SocialCommerce\Helper\Export;

use Magento\Framework\App\Helper\AbstractHelper;

class Order extends AbstractHelper
{

    const STORE_ID_FIELD_ID = 'store_id';

    const ORDER_ID_FIELD_ID = 'order_id';

    const DEFAULT_PAGE_SIZE = 25;

    protected $urlFinder;

    protected $storeManager;

    protected $searchCriteriaBuilder;

    protected $sortOrderBuilder;

    protected $filterBuilder;

    protected $orderRepository;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param $orderId
     * @param $storeId
     *
     * @return \Magento\Framework\Api\SearchCriteria
     */
    public function getShipmentSearchCriteriaForOrder($orderId, $storeId)
    {
        return $this->getSearchCriteria(
            $this->getSortOrder(self::ORDER_ID_FIELD_ID),
            [
                $this->getFilter(self::STORE_ID_FIELD_ID, $storeId, 'eq'),
                $this->getFilter(self::ORDER_ID_FIELD_ID, $orderId, 'eq')
            ]
        );
    }

    private function getSearchCriteria($sortOrder, $filters = [], $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilder->setPageSize($pageSize)->addSortOrder($sortOrder);
        foreach ($filters as $filter) {
            //add as separate groups to get AND join instead of OR
            $searchCriteriaBuilder = $searchCriteriaBuilder->addFilters([$filter]);
        }
        return $searchCriteriaBuilder->create();
    }

    /**
     * @param $fieldId
     * @param string $direction
     * @return \Magento\Framework\Api\AbstractSimpleObject
     */
    private function getSortOrder($fieldId, $direction = \Magento\Framework\Api\SortOrder::SORT_ASC)
    {
        return $this->sortOrderBuilder->setField($fieldId)->setDirection($direction)->create();
    }

    private function getFilter($fieldId, $value, $conditionType)
    {
        return $this->filterBuilder
            ->setField($fieldId)
            ->setValue($value)
            ->setConditionType($conditionType)
            ->create();
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return string
     */
    public function getOrderPostCode(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $postCode = '';
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $postCode = $shippingAddress->getPostcode();
        }

        return $postCode;
    }

    /**
     * @param $orderId
     * @return bool
     */
    public function getAllItemsShipped($orderId) {

        $items = $this->orderRepository->get($orderId)->getItems();

        $allItemsShipped = true;
        foreach ($items as $item) {
            // If the item has a parent item, that means it's just the associated Simple product, which doesn't actually
            //   track shipping and such, so we want to skip it.
            if ($item->getParentItem()) {
                continue;
            }

            $qtyOrdered = $item->getQtyOrdered();
            $qtyHandled = ($item->getQtyCanceled() + $item->getQtyRefunded() + $item->getQtyReturned() + $item->getQtyShipped());
            $qtyRemaining = $qtyOrdered - $qtyHandled;
            if ($qtyRemaining) {
                $allItemsShipped = false;
            }
        }

        return $allItemsShipped;
    }
}