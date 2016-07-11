<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 7/5/16
 * Time: 10:07 AM
 */

namespace TurnTo\SocialCommerce\Model\HistoricalOrders;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var \Magento\Eav\Model\ResourceModel\Form\Fieldset\Collection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * DataProvider constructor.
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param \Magento\Eav\Model\ResourceModel\Form\Fieldset\CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        \Magento\Eav\Model\ResourceModel\Form\Fieldset\CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
