<?php
/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TurnTo\SocialCommerce\Service\GoogleFeed;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use SimpleXMLElement;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Model\Config;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Config\Source\FeedFormat;
use TurnTo\SocialCommerce\Model\File;

class Client implements FeedClient
{
    /**
     * Response body from TurnTo servers on successful operation
     */
    const TURNTO_SUCCESS_RESPONSE = 'SUCCESS';
    const MAX_TRANSMISSION_ATTEMPTS = 3;
    /**
     * 500ms base retry delay, doubled for each retry attempt.
     */
    const TRANSMISSION_RETRY_DELAY_MICROSECONDS = 500000;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Monolog
     */
    protected $logger;
    /**
     * @var GuzzleClient
     */
    protected $client;
    /**
     * @var File
     */
    protected $file;

    public function __construct(
        Config $config,
        Monolog $logger,
        GuzzleClient $client,
        File $file
    ){
        $this->config = $config;
        $this->logger = $logger;
        $this->client = $client;
        $this->file = $file;
    }

    /**
     * @inheritdoc
     * @throws GuzzleException
     */
    public function transmitFeedFile($feedData, string $fileName, string $feedStyle, string $storeCode)
    {
        for ($attempt = 1; $attempt <= self::MAX_TRANSMISSION_ATTEMPTS; $attempt++) {
            $responseContents = '';
            $statusCode = null;
            $shouldRetry = false;
            $transmissionContext = [
                'store_code' => $storeCode,
                'feed_style' => $feedStyle,
                'file_name' => $fileName,
                'attempt' => $attempt,
                'max_attempts' => self::MAX_TRANSMISSION_ATTEMPTS
            ];

            try {
                if ($feedStyle === FeedFormat::GOOGLE_PRODUCT) {
                    if ($feedData instanceof SimpleXMLElement) {
                        $feedData = $feedData->asXML();
                    }
                    $path = "turnto/google-product_storecode_$storeCode.xml";
                    $this->file->writeFile($path, $feedData);
                } elseif ($feedStyle === FeedFormat::COMMERCE) {
                    $path = "turnto/commerce-product_storecode_$storeCode.tsv";
                    $this->file->writeFile($path, $feedData);
                }

                $response = $this->client->request(
                    'POST',
                    $this->config->getConfigValue(Config::PRODUCT_FEED_SUBMISSION_URL, $storeCode),
                    [
                        RequestOptions::HTTP_ERRORS => false,
                        RequestOptions::MULTIPART => [
                            [
                                'name' => 'siteKey',
                                'contents' => $this->config->getSiteKey($storeCode)
                            ],
                            [
                                'name' => 'authKey',
                                'contents' => $this->config->getAuthorizationKey($storeCode)
                            ],
                            [
                                'name' => 'feedStyle',
                                'contents' => $feedStyle
                            ],
                            [
                                'name' => 'file',
                                'contents' => $feedData,
                                'filename' => $fileName
                            ]
                        ]
                    ]
                );

                $statusCode = $response->getStatusCode();
                $responseContents = $response->getBody()->getContents();

                if ($feedStyle === FeedFormat::GOOGLE_PRODUCT) {
                    $path = "turnto/google-product_storecode_{$storeCode}_request.xml";
                    $this->file->writeFile($path, $responseContents);
                } elseif ($feedStyle === FeedFormat::COMMERCE) {
                    $path = "turnto/commerce-product_storecode_{$storeCode}_request.txt";
                    $this->file->writeFile($path, $responseContents);
                }

                if ($statusCode < 200 || $statusCode >= 300) {
                    $shouldRetry = $statusCode === 429 || ($statusCode >= 500 && $statusCode < 600);
                    throw new Exception(sprintf(
                        'Emplifi %s submission failed with status %d and response %s',
                        $fileName,
                        $statusCode,
                        $responseContents
                    ));
                }

                if (trim($responseContents) !== self::TURNTO_SUCCESS_RESPONSE) {
                    throw new Exception(sprintf(
                        'Emplifi %s submission failed with message: %s',
                        $fileName,
                        $responseContents
                    ));
                }

                return;
            } catch (Exception $e) {
                if ($e instanceof TransferException) {
                    $shouldRetry = true;
                }

                $transmissionContext['exception'] = $e;
                $transmissionContext['status_code'] = $statusCode;
                $transmissionContext['response'] = $responseContents;
                if ($shouldRetry && $attempt < self::MAX_TRANSMISSION_ATTEMPTS) {
                    $this->logger->warning(
                        'TurnTo catalog export transmission failed; retrying',
                        $transmissionContext
                    );
                    usleep($this->getRetryDelayMicroseconds($attempt));
                    continue;
                }

                $this->logger->error(
                    'TurnTo catalog feed transmission failed',
                    $transmissionContext
                );

                throw $e;
            }
        }
    }

    /**
     * Backoff in microseconds for a retry attempt.
     * attempt value starts at 1.
     *
     * @param int $attempt
     * @return int
     */
    protected function getRetryDelayMicroseconds(int $attempt): int
    {
        return self::TRANSMISSION_RETRY_DELAY_MICROSECONDS * (1 << ($attempt - 1));
    }
}
