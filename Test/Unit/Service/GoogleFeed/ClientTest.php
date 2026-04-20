<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Test\Unit\Service\GoogleFeed;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\File;
use TurnTo\SocialCommerce\Service\GoogleFeed\Client;

class TestableClient extends Client
{
    protected function getRetryDelayMicroseconds(int $attempt): int
    {
        return 0;
    }
}

class ClientTest extends TestCase
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var GuzzleClient
     */
    protected $client;
    /**
     * @var File
     */
    protected $file;
    /**
     * @var Monolog
     */
    protected $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->client = $this->createMock(GuzzleClient::class);
        $this->file = $this->createMock(File::class);
        $this->logger = $this->createMock(Monolog::class);

        $this->config->method('getConfigValue')->willReturn('https://feed.test/upload');
        $this->config->method('getSiteKey')->willReturn('site-key');
        $this->config->method('getAuthorizationKey')->willReturn('auth-key');
    }

    public function testTransmitFeedFileSuccessWritesRequestAndReturns()
    {
        $response = $this->createResponse(200, 'SUCCESS');
        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->file->expects($this->exactly(2))
            ->method('writeFile')
            ->withConsecutive(
                ['turnto/commerce-product_storecode_default.tsv', 'feed-content'],
                ['turnto/commerce-product_storecode_default_request.txt', 'SUCCESS']
            );
        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->never())->method('error');

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    public function testTransmitFeedFileRetriesOnServerErrorBeforeSuccess()
    {
        $serverError = $this->createResponse(500, 'Server unavailable');
        $success = $this->createResponse(200, 'SUCCESS');
        $this->client->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($serverError, $success);

        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->never())->method('error');

        $this->file->expects($this->any())->method('writeFile');

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    public function testTransmitFeedFileRetriesOnTooManyRequestsBeforeSuccess()
    {
        $rateLimitResponse = $this->createResponse(429, 'Too Many Requests');
        $success = $this->createResponse(200, 'SUCCESS');
        $this->client->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($rateLimitResponse, $success);

        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->never())->method('error');

        $this->file->expects($this->any())->method('writeFile');

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    public function testTransmitFeedFileDoesNotRetryForPermaErrorResponse()
    {
        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($this->createResponse(400, 'Invalid request'));

        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(Exception::class);

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    public function testTransmitFeedFileDoesNotRetryForErrorPayload()
    {
        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($this->createResponse(200, 'Validation failed'));

        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(Exception::class);

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    public function testTransmitFeedFileRetriesNetworkErrorUntilMaxAttempts()
    {
        $this->client->expects($this->exactly(3))
            ->method('request')
            ->willThrowException(new TransferException('network failure'));

        $this->logger->expects($this->exactly(2))->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(TransferException::class);

        $client = new TestableClient(
            $this->config,
            $this->logger,
            $this->client,
            $this->file
        );
        $client->transmitFeedFile(
            'feed-content',
            'catalog.tsv',
            FeedFormat::COMMERCE,
            'default'
        );
    }

    protected function createResponse(int $statusCode, string $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
