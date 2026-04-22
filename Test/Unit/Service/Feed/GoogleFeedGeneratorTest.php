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
use Magento\Directory\Model\Currency;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Export\CategoryPathResolver;
use TurnTo\SocialCommerce\Model\Export\Product as ExportProduct;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Service\Feed\GoogleFeedGenerator;

/**
 * Exposes protected feed helpers for unit testing.
 */
class TestableGoogleFeedGenerator extends GoogleFeedGenerator
{
    /**
     * @param SimpleXMLElement $entry
     * @param CatalogProduct $product
     * @param int|string $storeId
     * @param bool|CatalogProduct $parent
     * @return void
     */
    public function callAddProductToAtomFeed($entry, $product, $storeId, $parent)
    {
        $this->addProductToAtomFeed($entry, $product, $storeId, $parent);
    }

    /**
     * @inheritdoc
     */
    protected function getCategoryTreeString(CatalogProduct $product, $storeId)
    {
        return '';
    }
}

class GoogleFeedGeneratorTest extends TestCase
{
    /**
     * @var TestableGoogleFeedGenerator
     */
    protected $generator;

    /**
     * @var EavConfig
     */
    protected $eavConfig;
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
        $this->eavConfig = $this->createMock(EavConfig::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);
        $exportProduct = $this->createMock(ExportProduct::class);
        $this->categoryPathResolver = $this->createMock(CategoryPathResolver::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.test/p');

        $this->generator = new TestableGoogleFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $priceCurrency,
            $logger,
            $dateTimeFactory,
            $this->categoryPathResolver,
            $exportProduct
        );
    }

    public function testGetGtinValueReturnsLabelForSelectAttribute()
    {
        $product = $this->createMock(CatalogProduct::class);
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['usesSource'])
            ->getMock();
        $attribute->method('usesSource')->willReturn(true);
        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $product->method('getAttributeText')
            ->with('upc')
            ->willReturn('UPC Label');

        $gtinMap = [Gtin::UPC_ATTRIBUTE => 'upc'];
        $this->assertSame('UPC Label', $this->generator->getGtinValue($product, $gtinMap));
    }

    public function testGetGtinValueReturnsCommaSeparatedForMultiselect()
    {
        $product = $this->createMock(CatalogProduct::class);
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['usesSource'])
            ->getMock();
        $attribute->method('usesSource')->willReturn(true);
        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $product->method('getAttributeText')
            ->with('upc')
            ->willReturn(['Red', 'Blue']);

        $gtinMap = [Gtin::UPC_ATTRIBUTE => 'upc'];
        $this->assertSame('Red, Blue', $this->generator->getGtinValue($product, $gtinMap));
    }

    public function testGetGtinValueReturnsRawForNonSourceAttribute()
    {
        $product = $this->createMock(CatalogProduct::class);
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['usesSource'])
            ->getMock();
        $attribute->method('usesSource')->willReturn(false);
        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $product->method('getData')
            ->with('upc')
            ->willReturn('12345');

        $gtinMap = [Gtin::UPC_ATTRIBUTE => 'upc'];
        $this->assertSame('12345', $this->generator->getGtinValue($product, $gtinMap));
    }

    public function testGetGtinValueReturnsImplodedForArrayData()
    {
        $product = $this->createMock(CatalogProduct::class);
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['usesSource'])
            ->getMock();
        $attribute->method('usesSource')->willReturn(false);
        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $product->method('getData')
            ->with('upc')
            ->willReturn([1, 2]);

        $gtinMap = [Gtin::UPC_ATTRIBUTE => 'upc'];
        $this->assertSame('1, 2', $this->generator->getGtinValue($product, $gtinMap));
    }

    public function testAddProductToAtomFeedWritesConvertedFinalPriceWithCurrency()
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);

        // Ensure safe SKU encoding passthrough
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });

        // Set expectations for currency conversion and currency code retrieval
        $storeId = 1;
        $finalPrice = 12.34;

        $priceCurrency
            ->expects($this->once())
            ->method('convertAndRound')
            ->with($finalPrice, $storeId)
            ->willReturn($finalPrice);

        $currencyMock = $this->createPartialMock(
            Currency::class,
            ['getCurrencyCode']
        );
        $currencyMock->method('getCurrencyCode')->willReturn('USD');
        $priceCurrency->method('getCurrency')->with($storeId)->willReturn($currencyMock);

        $exportProduct = $this->createMock(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.com/p');

        $generator = new TestableGoogleFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $priceCurrency,
            $logger,
            $dateTimeFactory,
            $this->categoryPathResolver,
            $exportProduct
        );

        // Minimal product stub to satisfy feed requirements
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null); // avoid image helper chain
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);

        $entry = new SimpleXMLElement('<entry />');

        // Execute
        $generator->callAddProductToAtomFeed($entry, $product, $storeId, false);

        // Assert price element is present and formatted with currency code
        $xmlString = $entry->asXML();
        $this->assertIsString($xmlString);
        $this->assertStringContainsString(
            '<g:price xmlns:g="http://base.google.com/ns/1.0">12.34 USD</g:price>',
            $xmlString
        );
    }

    public function testAddProductToAtomFeedWritesEncodedItemGroupIdFromParent()
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);

        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return str_replace(
                array_keys(Product::TURNTO_CHARACTER_MAPPING),
                array_values(Product::TURNTO_CHARACTER_MAPPING),
                (string) $value
            );
        });

        $priceCurrency
            ->expects($this->once())
            ->method('convertAndRound')
            ->with(12.34, 1)
            ->willReturn(12.34);

        $currencyMock = $this->createPartialMock(
            Currency::class,
            ['getCurrencyCode']
        );
        $currencyMock->method('getCurrencyCode')->willReturn('USD');
        $priceCurrency->method('getCurrency')->with(1)->willReturn($currencyMock);

        $exportProduct = $this->createMock(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.com/p');

        $generator = new TestableGoogleFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $priceCurrency,
            $logger,
            $dateTimeFactory,
            $this->categoryPathResolver,
            $exportProduct
        );

        $parent = $this->createMock(CatalogProduct::class);
        $parent->method('getSku')->willReturn('PARENT/1');

        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('CHILD+1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null);
        $product->method('getFinalPrice')->willReturn(12.34);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);

        $entry = new SimpleXMLElement('<entry />');
        $generator->callAddProductToAtomFeed($entry, $product, 1, $parent);

        $xmlString = $entry->asXML();
        $this->assertStringContainsString(
            '<g:item_group_id xmlns:g="http://base.google.com/ns/1.0">PARENTFORWARDSLASH1</g:item_group_id>',
            $xmlString
        );
    }

    public function testAddProductReturnsFalseIfProductCanNotBeAdded()
    {
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('');

        $this->assertFalse($this->generator->addProduct($product, false, 1));
    }

    public function testAddProductReturnsTrueForValidProduct()
    {
        $storeId = 1;
        $finalPrice = 12.34;

        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $turntoProduct->method('turnToSafeEncoding')->willReturnCallback(function ($value) {
            return (string) $value;
        });
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);
        $dateTime = $this->createMock(\DateTime::class);
        $dateTime->method('format')->willReturn('2026-01-01T00:00:00+00:00');
        $dateTimeFactory->method('create')->willReturn($dateTime);

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

        $exportProduct = $this->createMock(ExportProduct::class);
        $exportProduct->method('getProductUrl')->willReturn('https://example.com/p');

        $generator = new TestableGoogleFeedGenerator(
            $config,
            $gtin,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $priceCurrency,
            $logger,
            $dateTimeFactory,
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

        $result = $generator->addProduct($product, false, $storeId);

        $this->assertTrue($result);
    }
}