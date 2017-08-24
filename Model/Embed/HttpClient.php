<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2017 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Embed;

class HttpClient
{
    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * HttpClient constructor.
     *
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     */
    public function __construct(
        \TurnTo\SocialCommerce\Logger\Monolog $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param $url
     * @return string
     * @throws \Exception
     */
    public function getTurnToHtml($url)
    {
        $errorMessage = __('Unable to load content.');
        try {
            $response = null;
            $httpClient = new \Magento\Framework\HTTP\ZendClient;
            $httpClient->setUri($url)
                ->setMethod(\Zend_Http_Client::GET);

            $response = $httpClient->request();

            if (!$response || !$response->isSuccessful()) {
                $e = new \Exception(__('TurnTo request responded with an error.'));
                $this->logger->error(
                    __('An error occurred while requesting content from TurnTo.'),
                    [
                        'exception' => $e,
                        'response' => $response ? 'null' : $response->getBody()
                    ]
                );
                return $errorMessage;
            }

            $body = $response->getBody();

            return $body;
        } catch (\Exception $e) {
            $this->logger->error(
                __('An error occurred while requesting content from TurnTo.'),
                [
                    'exception' => $e,
                    'response' => isset($response) ? $response->getBody() : 'null'
                ]
            );
            return $errorMessage;
        }
    }
}
