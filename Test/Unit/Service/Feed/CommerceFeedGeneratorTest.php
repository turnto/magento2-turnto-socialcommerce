<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\Feed;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Directory\Model\Currency;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Service\Feed\CommerceFeedGenerator;

/**
 * Exposes protected feed helpers for unit testing.
 */
class TestableCommerceFeedGenerator extends CommerceFeedGenerator
{
    /**
     * @param ConfigModel $config
     * @param Gtin $gtin
     * @param Image $imageHelper
     * @param Product $turntoProduct
     * @param EavConfig $eavConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Monolog $logger
     * @param ExportProduct $exportProduct
     */
    public function __construct(
        ConfigModel $config,
        Gtin $gtin,
        Image $imageHelper,
        Product $turntoProduct,
        EavConfig $eavConfig,
        PriceCurrencyInterface $priceCurrency,
        Monolog $logger,
        ExportProduct $exportProduct
    ) {
        parent::__construct(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $exportProduct
        );
    }

    /**
     * @param CatalogProduct $product
     * @param int|string|null $storeId
     * @param bool|CatalogProduct|null $parent
     * @return string
     */
    public function callGenerateProductLine($product, $storeId, $parent)
    {
        return $this->generateProductLine($product, $storeId, $parent);
    }

    /**
     * @param CatalogProduct $product
     * @return string
     */
    public function callGetMembers($product)
    {
        return $this->getMembers($product);
    }

    /**
     * @inheritdoc
     */
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        return 'Category 1 > Category 2';
    }
}

class CommerceFeedGeneratorTest extends TestCase
{
    /**
     * @var TestableCommerceFeedGenerator
     */
    protected $generator;

    protected function setUp(): void
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $eavConfig = $this->createMock(EavConfig::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);

        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });

        $exportProduct = $this->createMock(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.test/p');

        $this->generator = new TestableCommerceFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $exportProduct
        );
    }

    public function testGetMembersForBundleProduct()
    {
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn('bundle');

        $typeInstance = $this->createMock(BundleType::class);
        $typeInstance->method('getOptionsIds')->willReturn([1, 2]);

        $selection1 = $this->createMock(CatalogProduct::class);
        $selection1->method('getSku')->willReturn('CHILD-1');

        $selection2 = $this->createMock(CatalogProduct::class);
        $selection2->method('getSku')->willReturn('CHILD-2');

        $typeInstance->method('getSelectionsCollection')->willReturn([$selection1, $selection2]);

        $product->method('getTypeInstance')->willReturn($typeInstance);

        $members = $this->generator->callGetMembers($product);
        $this->assertEquals('CHILD-1,CHILD-2', $members);
    }

    public function testGetMembersForGroupedProduct()
    {
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn('grouped');

        $typeInstance = $this->createMock(GroupedType::class);

        $child1 = $this->createMock(CatalogProduct::class);
        $child1->method('getSku')->willReturn('GROUP-CHILD-1');

        $child2 = $this->createMock(CatalogProduct::class);
        $child2->method('getSku')->willReturn('GROUP-CHILD-2');

        $typeInstance->method('getAssociatedProducts')->willReturn([$child1, $child2]);

        $product->method('getTypeInstance')->willReturn($typeInstance);

        $members = $this->generator->callGetMembers($product);
        $this->assertEquals('GROUP-CHILD-1,GROUP-CHILD-2', $members);
    }

    public function testGenerateProductLine()
    {
        $storeId = 1;
        $finalPrice = 12.34;

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency
            ->method('convertAndRound')
            ->with($finalPrice, $storeId)
            ->willReturn($finalPrice);

        $currencyMock = $this->createPartialMock(
            Currency::class,
            ['getCurrencyCode']
        );
        $currencyMock->method('getCurrencyCode')->willReturn('USD');
        $priceCurrency->method('getCurrency')->with($storeId)->willReturn($currencyMock);

        // Re-instantiate with mocked priceCurrency to test generateProductLine
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);

        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });

        $exportProduct = $this->createMock(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.com/p');

        $generator = new TestableCommerceFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $exportProduct
        );

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('getTypeId')->willReturn('simple');

        $line = $generator->callGenerateProductLine($product, $storeId, false);

        $expectedCategoryJson = json_encode([
            ['id' => '10', 'name' => 'Category 1'],
            ['id' => '20', 'name' => 'Category 2']
        ]);

        $expectedLine = implode("\t", [
            'SKU-1',
            'Product Name',
            'https://example.com/p',
            '', // image_url
            '0', // stock (not in stock / no qty when is_in_stock is false)
            '1', // active
            $expectedCategoryJson,
            '', // virtual_parent_code
            '', // members
            '', // brand
            'USD',
            '12.34',
            '', // upc
            '', // ean
            ''  // mpn
        ]);

        $this->assertEquals($expectedLine, $line);
    }
}
