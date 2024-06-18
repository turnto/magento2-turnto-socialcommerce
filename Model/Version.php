<?php
/**
 * Copyright © Pixlee TurnTo, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Module\PackageInfoFactory;

class Version
{
    /**
     * Module name
     */
    public const MODULE_NAME = 'TurnTo_SocialCommerce';

    /**
     * @var ModuleList
     */
    protected $moduleList;
    /**
     * @var PackageInfoFactory
     */
    protected $packageInfoFactory;
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @param ModuleList $moduleList
     * @param PackageInfoFactory $packageInfoFactory
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ModuleList $moduleList,
        PackageInfoFactory $packageInfoFactory,
        ProductMetadataInterface $productMetadata
    ) {
        $this->moduleList = $moduleList;
        $this->packageInfoFactory = $packageInfoFactory;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Get package version with fallback to module setup_version
     * @return string
     */
    public function getModuleVersion()
    {
        $packageInfo = $this->packageInfoFactory->create();
        $version = $packageInfo->getVersion(self::MODULE_NAME);
        if (empty($version)) {
            $version = 'suv' . $this->moduleList->getOne(self::MODULE_NAME)['setup_version'];
        }

        return $version;
    }

    /**
     * Get the Magento version ex. 2.4.5
     * @return string
     */
    public function getMagentoVersion(){
        return $this->productMetadata->getVersion();
    }
}
