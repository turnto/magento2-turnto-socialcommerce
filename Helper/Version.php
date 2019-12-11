<?php
/**
 * @category    ClassyLlama
 * @package
 * @copyright   Copyright (c) 2019 Classy Llama Studios, LLC
 */

namespace TurnTo\SocialCommerce\Helper;


use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;

class Version extends AbstractHelper
{

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    public function __construct(Context $context,
                                \Magento\Framework\App\ProductMetadataInterface $productMetadata,
                                ModuleListInterface $moduleList)
    {
        parent::__construct($context);
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    public function getTurnToVersion(){
        $moduleVersions = $this->moduleList
            ->getOne('TurnTo_SocialCommerce');
        return $moduleVersions['setup_version'];
    }

    public function getMagentoVersion(){
      return $this->productMetadata->getVersion();
    }

}