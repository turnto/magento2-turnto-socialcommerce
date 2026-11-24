<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\Feed;

use LogicException;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Directory\Model\Currency;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfoBase;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Export\CategoryPathResolver;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Export\ProductRelations;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Service\Feed\AbstractFeedGenerator;

class CommerceFeedGeneratorTest extends TestCase
{
    /**
     * @var TestableCommerceFeedGenerator
     */
    protected $generator;
    /**
     * @var CategoryPathResolver
     */
    protected $categoryPathResolver;

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
        $this->categoryPathResolver = $this->createMock(CategoryPathResolver::class);

        $this->generator = new TestableCommerceFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $this->categoryPathResolver,
            $exportProduct
        );
    }

    /**
     * Mutates the generator's inherited protected $stream for error-path assertions.
     *
     * @param mixed $value
     * @return void
     */
    private function setGeneratorStream($value): void
    {
        $this->feedStreamReflection()->setValue($this->generator, $value);
    }

    /**
     * @return mixed
     */
    private function getGeneratorStream()
    {
        return $this->feedStreamReflection()->getValue($this->generator);
    }

    private function feedStreamReflection(): ReflectionProperty
    {
        $reflection = new ReflectionProperty(AbstractFeedGenerator::class, 'stream');
        if (\PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection;
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

    public function testGetMembersForBundleProductEncodesSpecialCharacterSku()
    {
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn('bundle');

        $typeInstance = $this->createMock(BundleType::class);
        $typeInstance->method('getOptionsIds')->willReturn([1, 2]);

        $selection1 = $this->createMock(CatalogProduct::class);
        $selection1->method('getSku')->willReturn('CHILD/1');

        $selection2 = $this->createMock(CatalogProduct::class);
        $selection2->method('getSku')->willReturn('CHILD+2');

        $typeInstance->method('getSelectionsCollection')->willReturn([$selection1, $selection2]);

        $product->method('getTypeInstance')->willReturn($typeInstance);

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return str_replace(
                array_keys(Product::TURNTO_CHARACTER_MAPPING),
                array_values(Product::TURNTO_CHARACTER_MAPPING),
                (string) $value
            );
        });
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $exportProduct = $this->createMock(ExportProduct::class);

        $generator = new TestableCommerceFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $this->categoryPathResolver,
            $exportProduct
        );

        $members = $generator->callGetMembers($product);
        $this->assertEquals('CHILDFORWARDSLASH1,CHILDPLUS2', $members);
    }

    public function testGetMembersForGroupedProductEncodesSpecialCharacterSku()
    {
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn('grouped');

        $typeInstance = $this->createMock(GroupedType::class);

        $child1 = $this->createMock(CatalogProduct::class);
        $child1->method('getSku')->willReturn('GROUP/CHILD#1');

        $child2 = $this->createMock(CatalogProduct::class);
        $child2->method('getSku')->willReturn('GROUP/CHILD#2');

        $typeInstance->method('getAssociatedProducts')->willReturn([$child1, $child2]);

        $product->method('getTypeInstance')->willReturn($typeInstance);

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return str_replace(
                array_keys(Product::TURNTO_CHARACTER_MAPPING),
                array_values(Product::TURNTO_CHARACTER_MAPPING),
                (string) $value
            );
        });
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $exportProduct = $this->createMock(ExportProduct::class);

        $generator = new TestableCommerceFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $eavConfig,
            $priceCurrency,
            $logger,
            $this->categoryPathResolver,
            $exportProduct
        );

        $members = $generator->callGetMembers($product);
        $this->assertEquals('GROUPFORWARDSLASHCHILDHASH1,GROUPFORWARDSLASHCHILDHASH2', $members);
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
            $this->categoryPathResolver,
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
            ['id' => '100', 'name' => 'Category 1'],
            ['id' => '200', 'name' => 'Category 2']
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

    public function testGenerateProductLineSkipsVirtualParentForSpecialCharacterSku()
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

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return str_replace(
                array_keys(Product::TURNTO_CHARACTER_MAPPING),
                array_values(Product::TURNTO_CHARACTER_MAPPING),
                (string) $value
            );
        });
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);

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
            $this->categoryPathResolver,
            $exportProduct
        );

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU/1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getData')->willReturnCallback(function ($value) {
            if ($value === 'is_in_stock') {
                return 1;
            }
            if ($value === 'qty') {
                return 2;
            }
            return null;
        });

        $line = $generator->callGenerateProductLine($product, $storeId, false);

        $expectedCategoryJson = json_encode([
            ['id' => '100', 'name' => 'Category 1'],
            ['id' => '200', 'name' => 'Category 2']
        ]);

        $expectedLine = implode("\t", [
            'SKUFORWARDSLASH1',
            'Product Name',
            'https://example.com/p',
            '', // image_url
            '2', // stock (in stock)
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

    public function testGenerateProductLineEncodesVirtualParentFromParentSku()
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

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return str_replace(
                array_keys(Product::TURNTO_CHARACTER_MAPPING),
                array_values(Product::TURNTO_CHARACTER_MAPPING),
                (string) $value
            );
        });
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);

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
            $this->categoryPathResolver,
            $exportProduct
        );

        $parent = $this->createMock(CatalogProduct::class);
        $parent->method('getSku')->willReturn('PARENT/1');

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('CHILD+1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getData')->willReturnCallback(function ($value) {
            if ($value === 'is_in_stock') {
                return 1;
            }
            if ($value === 'qty') {
                return 2;
            }
            return null;
        });

        $line = $generator->callGenerateProductLine($product, $storeId, $parent);

        $expectedCategoryJson = json_encode([
            ['id' => '100', 'name' => 'Category 1'],
            ['id' => '200', 'name' => 'Category 2']
        ]);

        $expectedLine = implode("\t", [
            'CHILDPLUS1',
            'Product Name',
            'https://example.com/p',
            '', // image_url
            '2', // stock (in stock)
            '1', // active
            $expectedCategoryJson,
            'PARENTFORWARDSLASH1', // virtual_parent_code
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

    /**
     * @dataProvider finalPriceTypeProvider
     */
    #[DataProvider('finalPriceTypeProvider')]
    public function testGetExportPriceConvertsTheFinalPriceOfTypesWithTheirOwnPrice(string $typeId): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects($this->once())
            ->method('convertAndRound')
            ->with(12.34, 1)
            ->willReturn(15.0);
        $priceCurrency->expects($this->never())->method('round');

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getFinalPrice')->willReturn(12.34);
        $product->expects($this->never())->method('getPriceInfo');

        $this->assertSame(15.0, $this->createGenerator($priceCurrency)->callGetExportPrice($product, 1));
    }

    public static function finalPriceTypeProvider(): array
    {
        return [
            'simple' => ['simple'],
            'virtual' => ['virtual'],
            'downloadable' => ['downloadable'],
        ];
    }

    /**
     * @dataProvider compositePriceTypeProvider
     */
    #[DataProvider('compositePriceTypeProvider')]
    public function testGetExportPriceUsesThePricingFrameworkForCompositeTypes(string $typeId): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects($this->never())->method('convertAndRound');
        $priceCurrency->expects($this->once())->method('round')->with(9.99)->willReturn(9.99);

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->expects($this->never())->method('getFinalPrice');
        $product->method('getPriceInfo')->willReturn($this->createPriceInfo(9.99));

        $this->assertSame(9.99, $this->createGenerator($priceCurrency)->callGetExportPrice($product, 1));
    }

    public static function compositePriceTypeProvider(): array
    {
        return [
            'configurable' => ['configurable'],
            'bundle' => ['bundle'],
            'grouped' => ['grouped'],
        ];
    }

    public function testGetExportPriceFallsBackToTheFinalPriceWhenThePricingFrameworkFails(): void
    {
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndRound')->willReturnMap([[5.0, 1, 5.0]]);
        $logger = $this->createMock(Monolog::class);
        $logger->expects($this->once())->method('error');

        $product = $this->createStub(CatalogProduct::class);
        $product->method('getTypeId')->willReturn('bundle');
        $product->method('getPriceInfo')->willThrowException(new \Exception('no selections'));
        $product->method('getFinalPrice')->willReturn(5.0);

        $this->assertSame(5.0, $this->createGenerator($priceCurrency, $logger)->callGetExportPrice($product, 1));
    }

    /**
     * @dataProvider stockProvider
     */
    #[DataProvider('stockProvider')]
    public function testGenerateProductLineStock(string $typeId, int $isInStock, $qty, string $expectedStock): void
    {
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndRound')->willReturn(10.0);
        $priceCurrency->method('round')->willReturn(10.0);
        $currency = $this->createStub(Currency::class);
        $currency->method('getCurrencyCode')->willReturn('USD');
        $priceCurrency->method('getCurrency')->willReturn($currency);

        $product = $this->createStub(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getFinalPrice')->willReturn(10.0);
        $product->method('getPriceInfo')->willReturn($this->createPriceInfo(10.0));
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        if ($typeId === 'bundle') {
            $typeInstance = $this->createStub(BundleType::class);
            $typeInstance->method('getOptionsIds')->willReturn([]);
            $typeInstance->method('getSelectionsCollection')->willReturn([]);
            $product->method('getTypeInstance')->willReturn($typeInstance);
        } elseif ($typeId === 'grouped') {
            $typeInstance = $this->createStub(GroupedType::class);
            $typeInstance->method('getAssociatedProducts')->willReturn([]);
            $product->method('getTypeInstance')->willReturn($typeInstance);
        }
        $product->method('getData')->willReturnCallback(function ($key) use ($isInStock, $qty) {
            if ($key === 'is_in_stock') {
                return $isInStock;
            }
            if ($key === 'qty') {
                return $qty;
            }
            return null;
        });

        $line = explode("\t", $this->createGenerator($priceCurrency)->callGenerateProductLine($product, 1, false));

        $this->assertSame($expectedStock, $line[4]);
    }

    public static function stockProvider(): array
    {
        return [
            'simple in stock sends its quantity' => ['simple', 1, '7.0000', '7'],
            'simple out of stock' => ['simple', 0, '7.0000', '0'],
            'virtual without managed stock is available' => ['virtual', 1, '0.0000', '1'],
            'downloadable without a stock row is unavailable' => ['downloadable', 0, null, '0'],
            'configurable in stock has no quantity of its own' => ['configurable', 1, '0.0000', '1'],
            'bundle in stock has no quantity of its own' => ['bundle', 1, '0.0000', '1'],
            'grouped in stock has no quantity of its own' => ['grouped', 1, '0.0000', '1'],
            'grouped out of stock' => ['grouped', 0, '0.0000', '0'],
        ];
    }

    public function testGenerateProductLineSharesThePoolParentsOwnSkuInsteadOfListingMembers(): void
    {
        $child = $this->createStub(CatalogProduct::class);
        $child->method('getSku')->willReturn('CHILD-1');
        $typeInstance = $this->createStub(GroupedType::class);
        $typeInstance->method('getAssociatedProducts')->willReturn([$child]);
        $kit = $this->createFeedProduct('GROUP-1', 'grouped');
        $kit->method('getTypeInstance')->willReturn($typeInstance);
        $pool = $this->createFeedProduct('GROUP-1', 'grouped', [ProductRelations::POOL_PARENT_FIELD => true]);
        $pool->method('getTypeInstance')->willReturn($typeInstance);
        $generator = $this->createGenerator($this->createFeedPriceCurrency());

        $kitLine = explode("\t", $generator->callGenerateProductLine($kit, 1, false));
        $poolLine = explode("\t", $generator->callGenerateProductLine($pool, 1, false));

        $this->assertSame(['', 'CHILD-1'], [$kitLine[7], $kitLine[8]]);
        $this->assertSame(['GROUP-1', ''], [$poolLine[7], $poolLine[8]]);
    }

    public function testGenerateProductLineExportsTheUrlOfTheParentOrUrlProduct(): void
    {
        $parent = $this->createFeedProduct('PARENT-1', 'configurable');
        $urlProduct = $this->createFeedProduct('GROUP-1', 'grouped');
        $product = $this->createFeedProduct('CHILD-1', 'simple', [ProductRelations::URL_PRODUCT_FIELD => $urlProduct]);
        $exportProduct = $this->createStub(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturnCallback(function ($urlSource) {
            return 'https://example.com/' . $urlSource->getSku();
        });
        $generator = $this->createGenerator($this->createFeedPriceCurrency(), null, $exportProduct);

        $withUrlProduct = explode("\t", $generator->callGenerateProductLine($product, 1, false));
        $withParent = explode("\t", $generator->callGenerateProductLine($product, 1, $parent));

        $this->assertSame('https://example.com/GROUP-1', $withUrlProduct[2]);
        $this->assertSame('', $withUrlProduct[7], 'Borrowing a URL does not share reviews.');
        $this->assertSame('https://example.com/PARENT-1', $withParent[2]);
        $this->assertSame('PARENT-1', $withParent[7]);
    }

    /**
     * @param string $sku
     * @param string $typeId
     * @param array $data
     * @return CatalogProduct
     */
    private function createFeedProduct(string $sku, string $typeId, array $data = []): CatalogProduct
    {
        $data += ['is_in_stock' => 1, 'qty' => '1.0000'];
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getName')->willReturn('Product Name');
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getFinalPrice')->willReturn(10.0);
        $product->method('getPriceInfo')->willReturn($this->createPriceInfo(10.0));
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('getData')->willReturnCallback(function ($key) use ($data) {
            return $data[$key] ?? null;
        });

        return $product;
    }

    private function createFeedPriceCurrency(): PriceCurrencyInterface
    {
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndRound')->willReturn(10.0);
        $priceCurrency->method('round')->willReturn(10.0);
        $currency = $this->createStub(Currency::class);
        $currency->method('getCurrencyCode')->willReturn('USD');
        $priceCurrency->method('getCurrency')->willReturn($currency);

        return $priceCurrency;
    }

    /**
     * @param PriceCurrencyInterface $priceCurrency
     * @param Monolog|null $logger
     * @param ExportProduct|null $exportProduct
     * @return TestableCommerceFeedGenerator
     */
    private function createGenerator(
        PriceCurrencyInterface $priceCurrency,
        ?Monolog $logger = null,
        ?ExportProduct $exportProduct = null
    ): TestableCommerceFeedGenerator {
        $turntoProduct = $this->createStub(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });
        if ($exportProduct === null) {
            $exportProduct = $this->createStub(ExportProduct::class);
            $exportProduct->method('getProductUrl')->willReturn('https://example.com/p');
        }
        $categoryPathResolver = $this->createStub(CategoryPathResolver::class);

        return new TestableCommerceFeedGenerator(
            $this->createStub(ConfigModel::class),
            $this->createStub(Gtin::class),
            $this->createStub(Image::class),
            $turntoProduct,
            $this->createStub(EavConfig::class),
            $priceCurrency,
            $logger ?? $this->createStub(Monolog::class),
            $categoryPathResolver,
            $exportProduct
        );
    }

    /**
     * @param float $finalPrice
     * @return PriceInfoBase
     */
    private function createPriceInfo(float $finalPrice): PriceInfoBase
    {
        $price = $this->createStub(PriceInterface::class);
        $price->method('getValue')->willReturn($finalPrice);
        $priceInfo = $this->createStub(PriceInfoBase::class);
        $priceInfo->method('getPrice')->willReturnMap([['final_price', $price]]);

        return $priceInfo;
    }

    public function testAddProductReturnsFalseIfProductCanNotBeAddedToFeed()
    {
        $storeId = 1;
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('');

        $this->assertFalse($this->generator->addProduct($product, false, $storeId));
    }

    public function testAddProductReturnsTrueForValidProduct()
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

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });
        $eavConfig = $this->createMock(EavConfig::class);
        $logger = $this->createMock(Monolog::class);
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
            $this->categoryPathResolver,
            $exportProduct
        );

        $store = $this->createMock(Store::class);
        $store->method('getName')->willReturn('Test Store');
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $generator->beginFeed($store);

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null);
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getData')->willReturnCallback(function ($value) {
            if ($value === 'is_in_stock') {
                return 1;
            }
            if ($value === 'qty') {
                return 2;
            }
            return null;
        });

        $result = $generator->addProduct($product, false, $storeId);

        $this->assertTrue($result);
    }

    public function testFinishFeedThrowsWhenCalledWithoutBeginFeed()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Feed stream is not initialized. Call beginFeed() before finishFeed().');

        $this->generator->finishFeed();
    }

    public function testFinishFeedCleansUpStateOnStreamError()
    {
        $store = $this->createMock(Store::class);
        $store->method('getName')->willReturn('Test Store');
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $this->generator->beginFeed($store);
        $this->assertTrue($this->generator->isFeedOpen());

        $this->setGeneratorStream(null);

        try {
            $this->generator->finishFeed();
            $this->fail('Expected finishFeed to throw an exception when stream resource is invalid');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Feed stream is invalid', $e->getMessage());
        } finally {
            $this->setGeneratorStream(null);
            $this->assertFalse($this->generator->isFeedOpen());
            $this->assertNull($this->getGeneratorStream());
        }
    }
}
