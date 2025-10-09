<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model\Export;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Directory\Model\Currency;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config as ConfigModel;
use TurnTo\SocialCommerce\Model\Config\Gtin;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;
use TurnTo\SocialCommerce\Test\Unit\Model\Export\TestableCatalog;

class CatalogTest extends TestCase
{
    /**
     * @var Catalog
     */
    protected $catalog;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * Is called before running a test
     * @throws Exception
     */
    protected function setUp(): void
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $this->eavConfig = $this->createMock(EavConfig::class);
        $feedClient = $this->createMock(FeedClient::class);
        $emulation = $this->createMock(Emulation::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);

        $this->catalog = new Catalog(
            $config,
            $gtin,
            $storeManager,
            $collectionFactory,
            $dateTimeFactory,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $feedClient,
            $emulation,
            $priceCurrency,
            $logger
        );
    }

    public function testIsCatalogClass(){
        $this->assertInstanceOf(Catalog::class, $this->catalog);
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
        $this->assertSame('UPC Label', $this->catalog->getGtinValue($product, $gtinMap));
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
        $this->assertSame('Red, Blue', $this->catalog->getGtinValue($product, $gtinMap));
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
        $this->assertSame('12345', $this->catalog->getGtinValue($product, $gtinMap));
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
        $this->assertSame('1, 2', $this->catalog->getGtinValue($product, $gtinMap));
    }

    public function testAddProductToAtomFeedWritesConvertedFinalPriceWithCurrency()
    {
        $config = $this->createMock(ConfigModel::class);
        $gtin = $this->createMock(Gtin::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $dateTimeFactory = $this->createMock(DateTimeFactory::class);
        $imageHelper = $this->createMock(Image::class);
        $turntoProduct = $this->createMock(Product::class);
        $feedClient = $this->createMock(FeedClient::class);
        $emulation = $this->createMock(Emulation::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $logger = $this->createMock(Monolog::class);

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

        // Build the catalog instance under test
        $catalog = new TestableCatalog(
            $config,
            $gtin,
            $storeManager,
            $collectionFactory,
            $dateTimeFactory,
            $imageHelper,
            $turntoProduct,
            $this->eavConfig,
            $feedClient,
            $emulation,
            $priceCurrency,
            $logger
        );

        // Minimal product stub to satisfy feed requirements
        $product = $this->createMock(CatalogProduct::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getProductUrl')->willReturn('https://example.com/p');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getImage')->willReturn(null); // avoid image helper chain
        $product->method('getFinalPrice')->willReturn($finalPrice);
        $product->method('getCustomAttribute')->willReturn(null);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);

        $entry = new SimpleXMLElement('<entry />');

        // Execute
        $catalog->callAddProductToAtomFeed($entry, $product, $storeId, false);

        // Assert price element is present and formatted with currency code
        $xmlString = $entry->asXML();
        $this->assertIsString($xmlString);
        $this->assertStringContainsString('<price>12.34 USD</price>', $xmlString);
    }
}
