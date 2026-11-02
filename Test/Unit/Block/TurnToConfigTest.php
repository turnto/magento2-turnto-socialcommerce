<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Block;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\TestCase;
use TurnTo\SocialCommerce\Api\TurnToConfigDataSourceInterface;
use TurnTo\SocialCommerce\Block\TurnToConfig;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Product;
use TurnTo\SocialCommerce\Model\Version;

class TurnToConfigTest extends TestCase
{
    public function testConfigJsonIsPlainJsonWithoutTeaserCallbacks(): void
    {
        $config = json_decode($this->createBlock()->getConfigJson(), true);

        $this->assertSame('en_US', $config['locale']);
        $this->assertSame(['magentoVersion' => '2.4.9', 'turnToCart' => '3.9.1'], $config['extensionVersion']);
        $this->assertSame(['userDataFn' => null], $config['sso']);
        $this->assertSame(['suppress' => true], $config['commentCapture']);
        $this->assertSame('https://widgets.turnto.test', $config['baseUrl']);
        $this->assertSame('site-key', $config['siteKey']);
        $this->assertArrayNotHasKey('teaser', $config);
    }

    public function testConfiguredDataTakesPrecedenceOverGeneratedValues(): void
    {
        $dataSource = $this->createStub(TurnToConfigDataSourceInterface::class);
        $dataSource->method('getData')->willReturn(['locale' => 'fr_FR', 'teaser' => ['showReviews' => null]]);

        $config = json_decode($this->createBlock(['config_data' => $dataSource])->getConfigJson(), true);

        $this->assertSame('fr_FR', $config['locale']);
        $this->assertSame(['showReviews' => null], $config['teaser']);
    }

    public function testTeaserSelectorsDefaultToTheProductPageTabs(): void
    {
        $block = $this->createBlock();

        $this->assertSame(
            ['reviews' => '#tab-label-reviews-title', 'qa' => '#tab-label-turnto_qa-title'],
            $block->getTeaserSelectors()
        );
        $this->assertSame(
            '{"reviews":"#tab-label-reviews-title","qa":"#tab-label-turnto_qa-title"}',
            $block->getTeaserSelectorsJson()
        );
    }

    public function testTeaserSelectorsCanBeSetFromLayout(): void
    {
        $block = $this->createBlock([
            'teaser_reviews_tab_selector' => '.reviews-tab',
            'teaser_qa_tab_selector' => '.qa-tab',
        ]);

        $this->assertSame(['reviews' => '.reviews-tab', 'qa' => '.qa-tab'], $block->getTeaserSelectors());
    }

    public function testDeprecatedJavaScriptConfigStillEmbedsTheTeaserCallbacks(): void
    {
        $javaScript = $this->createBlock(['teaser_reviews_tab_selector' => '.reviews-tab'])->getJavaScriptConfig();

        $this->assertStringContainsString(
            '"teaser":{"showReviews": function(){jQuery(".reviews-tab").click()},'
            . '"showQa": function(){jQuery("#tab-label-turnto_qa-title").click()}}',
            $javaScript
        );
        $this->assertStringContainsString('"locale":"en_US"', $javaScript);
    }

    protected function createBlock(array $data = []): TurnToConfig
    {
        $config = $this->createStub(Config::class);
        $config->method('getConfigBool')->willReturn(false);
        $config->method('getConfigValue')->willReturnCallback(function ($path) {
            return $path === Config::PRODUCT_WIDGET_URL ? 'https://widgets.turnto.test/' : null;
        });
        $config->method('getSiteKey')->willReturn('site-key');

        $localeResolver = $this->createStub(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn('en_US');

        $version = $this->createStub(Version::class);
        $version->method('getMagentoVersion')->willReturn('2.4.9');
        $version->method('getModuleVersion')->willReturn('3.9.1');

        return new TurnToConfig(
            $this->createStub(Context::class),
            $config,
            $localeResolver,
            $this->createStub(Data::class),
            $version,
            $this->createStub(Product::class),
            new Json(),
            $data
        );
    }
}
