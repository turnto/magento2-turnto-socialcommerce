<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Framework\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Model\Product;

class ProductTest extends TestCase
{
    public function testGetProductSkuEncodesTheCurrentProductsSku(): void
    {
        $product = $this->createStub(ProductInterface::class);
        $product->method('getSku')->willReturn('TEE/BLUE#1');
        $locator = $this->createStub(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($product);

        $this->assertSame('TEEFORWARDSLASHBLUEHASH1', (new Product($locator))->getProductSku());
    }

    public function testGetProductSkuIsEmptyOutsideAProductContext(): void
    {
        $locator = $this->createStub(LocatorInterface::class);
        $locator->method('getProduct')->willThrowException(new NotFoundException(__('Product was not registered')));

        $this->assertSame('', (new Product($locator))->getProductSku());
    }

    public function testGetProductSkuIsEmptyForAProductWithoutASku(): void
    {
        $product = $this->createStub(ProductInterface::class);
        $product->method('getSku')->willReturn(null);
        $locator = $this->createStub(LocatorInterface::class);
        $locator->method('getProduct')->willReturn($product);

        $this->assertSame('', (new Product($locator))->getProductSku());
    }

    public function testSafeEncodingRoundTrips(): void
    {
        $product = new Product($this->createStub(LocatorInterface::class));
        $sku = 'A/B#C\\D>E<F&G=H%I!J.K+L';

        $encoded = $product->turnToSafeEncoding($sku);

        $this->assertSame(
            'AFORWARDSLASHBHASHCBACKSLASHDGREATERTHANELESSTHANFAMPERSIGNGEQUALSHPERCENTIEXCLAMATIONJPERIODKPLUSL',
            $encoded
        );
        $this->assertSame($sku, $product->turnToSafeDecoding($encoded));
    }
}
