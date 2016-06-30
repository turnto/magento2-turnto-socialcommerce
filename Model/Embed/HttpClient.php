<?php

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
        try {
            $response = null;
            $httpClient = new \Magento\Framework\HTTP\ZendClient;
            $httpClient->setUri($url)
                ->setMethod(\Zend_Http_Client::GET);

            $response = $httpClient->request();

            if (!$response || !$response->isSuccessful()) {
                $errorMessage = __('This content could not be retrieved at this time.');
                $e = new \Exception(__('TurnTo request responded with an error.'));
                $this->logger->error(__('An error occurred while requesting content from TurnTo.'),
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
            $this->logger->error(__('An error occurred while requesting content from TurnTo.'),
                [
                    'exception' => $e,
                    'response' => $response ? 'null' : $response->getBody()
                ]
            );
            return $errorMessage;
        }
    }
}
