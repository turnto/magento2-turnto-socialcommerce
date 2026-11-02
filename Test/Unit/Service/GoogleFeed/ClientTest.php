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
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\File;
use TurnTo\SocialCommerce\Service\GoogleFeed\Client;

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
    /**
     * @var string[]
     */
    protected $feedFiles = [];

    protected function setUp(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->client = $this->createMock(GuzzleClient::class);
        $this->file = $this->createStub(File::class);
        $this->logger = $this->createMock(Monolog::class);

        $this->config->method('getConfigValue')->willReturn('https://feed.test/upload');
        $this->config->method('getSiteKey')->willReturn('site-key');
        $this->config->method('getAuthorizationKey')->willReturn('auth-key');
    }

    public function testTransmitFeedFileSuccessWritesRequestAndReturns()
    {
        $response = $this->createResponse(200, 'SUCCESS');
        $writeCalls = [];
        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->file = $this->createMock(File::class);
        $this->file->expects($this->exactly(2))
            ->method('writeFile')
            ->willReturnCallback(function ($fileName, $contents) use (&$writeCalls) {
                $writeCalls[] = [$fileName, $contents];
            });
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

        $this->assertSame(
            [
                ['turnto/commerce-product_storecode_default.tsv', 'feed-content'],
                ['turnto/commerce-product_storecode_default_request.txt', 'SUCCESS']
            ],
            $writeCalls
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

    public function testTransmitFeedFromPathStreamsTheFileAndClosesTheStream()
    {
        $feedPath = $this->createFeedFile("ORDERID\n100000001\n");
        $sentStreams = [];
        $sentContents = [];
        $this->client->expects($this->once())
            ->method('request')
            ->willReturnCallback(function ($method, $uri, array $options) use (&$sentStreams, &$sentContents) {
                $fileField = $options[RequestOptions::MULTIPART][3];
                $this->assertSame('file', $fileField['name']);
                $this->assertSame('historical-orders-feed.tsv', $fileField['filename']);
                $sentStreams[] = $fileField['contents'];
                $sentContents[] = stream_get_contents($fileField['contents']);

                return $this->createResponse(200, 'SUCCESS');
            });
        $this->file = $this->createMock(File::class);
        $this->file->expects($this->never())->method('writeFile');
        $this->logger->expects($this->never())->method('error');

        $client = new TestableClient($this->config, $this->logger, $this->client, $this->file);
        $client->transmitFeedFromPath($feedPath, 'historical-orders-feed.tsv', 'tab-style.1', 'default');

        $this->assertSame(["ORDERID\n100000001\n"], $sentContents);
        $this->assertFalse(is_resource($sentStreams[0]), 'The file handle is closed after the request.');
    }

    public function testTransmitFeedFromPathReopensTheFileForEachRetry()
    {
        $feedPath = $this->createFeedFile("ORDERID\n");
        $sentContents = [];
        $this->client->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $uri, array $options) use (&$sentContents) {
                $sentContents[] = stream_get_contents($options[RequestOptions::MULTIPART][3]['contents']);

                return count($sentContents) === 1
                    ? $this->createResponse(503, 'Unavailable')
                    : $this->createResponse(200, 'SUCCESS');
            });
        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->never())->method('error');

        $client = new TestableClient($this->config, $this->logger, $this->client, $this->file);
        $client->transmitFeedFromPath($feedPath, 'historical-orders-feed.tsv', 'tab-style.1', 'default');

        $this->assertSame(["ORDERID\n", "ORDERID\n"], $sentContents);
    }

    public function testTransmitFeedFromPathFailsWithoutRetryingWhenTheFileIsMissing()
    {
        $this->client->expects($this->never())->method('request');
        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(RuntimeException::class);

        $client = new TestableClient($this->config, $this->logger, $this->client, $this->file);
        $client->transmitFeedFromPath(
            sys_get_temp_dir() . '/turnto-missing-feed-' . uniqid() . '.tsv',
            'historical-orders-feed.tsv',
            'tab-style.1',
            'default'
        );
    }

    protected function createFeedFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'turnto-feed-');
        file_put_contents($path, $contents);
        $this->feedFiles[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->feedFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->feedFiles = [];
    }

    protected function createResponse(int $statusCode, string $body): ResponseInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
