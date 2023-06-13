<?php

namespace TurnTo\SocialCommerce\Service\GoogleFeed;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use TurnTo\SocialCommerce\Api\FeedClient;
use TurnTo\SocialCommerce\Helper\Config;
use TurnTo\SocialCommerce\Logger\Monolog;
use TurnTo\SocialCommerce\Model\Export\Catalog;
use TurnTo\SocialCommerce\Model\File;

class Client implements FeedClient
{
    /**
     * Response body from TurnTo servers on successful operation
     */
    const TURNTO_SUCCESS_RESPONSE = 'SUCCESS';
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
        Config       $config,
        Monolog      $logger,
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
     */
    public function transmitFeedFile($feedData, string $fileName, string $feedStyle, string $storeCode)
    {
        $responseContents = 'win';
        try {
            if ($feedStyle === Catalog::FEED_STYLE) {
                $feedData = $feedData->asXML();
                $path = "turnto/google-product_storecode_{$storeCode}.xml";
                $this->file->writeFile($path, $feedData);
            }

            $response = $this->client->request('POST', $this->config->getFeedUploadAddress($storeCode), [
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
                        'name'     => 'file',
                        'contents' => $feedData,
                        'filename' => $fileName
                    ]
                ]
            ]);

            $responseContents = $response->getBody()->getContents();
            if ($feedStyle === Catalog::FEED_STYLE) {
                $path = "turnto/google-product_storecode_{$storeCode}_request.xml";
                $this->file->writeFile($path, $responseContents);
            }

            //It is possible to get a status 200 message who's body is an error message from TurnTo
            if ($responseContents !== self::TURNTO_SUCCESS_RESPONSE) {
                throw new Exception("TurnTo $fileName submission failed with message: $responseContents");
            }
        } catch (Exception $e) {
            $this->logger->error(
                "An error occurred while transmitting $fileName to TurnTo. Error: ",
                [
                    'exception' => $e,
                    'response' => $responseContents
                ]
            );
            throw $e;
        }
    }
}
