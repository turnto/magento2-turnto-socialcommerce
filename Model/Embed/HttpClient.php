<?php

namespace TurnTo\SocialCommerce\Model\Embed;

class HttpClient
{
    /**
     * @var \Zend\Http\Client
     */
    protected $httpClient;

    /**
     * @var \TurnTo\SocialCommerce\Logger\Monolog
     */
    protected $logger;

    /**
     * HttpClient constructor.
     *
     * @param \Zend\Http\Client $httpClient
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     */
    public function __construct(
        \Zend\Http\Client $httpClient,
        \TurnTo\SocialCommerce\Logger\Monolog $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * @param $url
     * @return string
     * @throws \Exception
     */
    public function getTurnToHtml($url)
    {
        try{
            $response = null;
            $this->httpClient
                ->setUri($url)
                ->setMethod(\Zend_Http_Client::GET);

            $response = $this->httpClient->send();

            if (!$response || !$response->isSuccess()) {
                $errorMessage = "This content could not be retrieved at this time.";
                $e = new \Exception('TurnTo request responded with an error.');
                $this->logger->error('An error occurred while requesting content from TurnTo.',
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
            $this->logger->error('An error occurred while requesting content from TurnTo.',
                [
                    'exception' => $e,
                    'response' => $response ? 'null' : $response->getBody()
                ]
            );
            throw $e;
        }
    }
}
