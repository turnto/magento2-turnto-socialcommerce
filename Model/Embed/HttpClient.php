<?php
/**
 * TurnTo_SocialCommerce
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Embed;

use Magento\Framework\HTTP\ZendClientFactory;
use TurnTo\SocialCommerce\Logger\Monolog as TurnToLogger;

class HttpClient
{
    /**
     * @var TurnToLogger
     */
    protected $logger;

    /**
     * @var ZendClientFactory
     */
    protected $httpClientFactory;

    /**
     * @param TurnToLogger      $logger
     * @param ZendClientFactory $httpClientFactory
     */
    public function __construct(TurnToLogger $logger, ZendClientFactory $httpClientFactory)
    {
        $this->logger = $logger;
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * @param $url
     *
     * @return string
     */
    public function getTurnToHtml($url)
    {
        $errorMessage = __('Unable to load content.');

        try {
            $httpClient = $this->httpClientFactory->create();
            $response = $httpClient->setUri($url)->setMethod(\Zend_Http_Client::GET)->request();

            if (!$response->isSuccessful()) {
                $this->logger->error(
                    __('TurnTo request responded with an error.'),
                    [
                        'requestUrl' => $url,
                        'responseBody' => $response->getBody()
                    ]
                );

                return $errorMessage;
            }

            return $response->getBody();
        } catch (\Zend_Http_Client_Exception $exception) {
            $this->logger->error(
                __('An error occurred while requesting content from TurnTo.'),
                ['exception' => $exception]
            );

            return $errorMessage;
        }
    }
}
