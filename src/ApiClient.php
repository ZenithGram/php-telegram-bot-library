<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Form;

class ApiClient
{
    private const API_BASE_URL = 'https://api.telegram.org';
    // Стандартный таймаут для обычных запросов (sendMessage и т.д.)
    private const DEFAULT_TIMEOUT = 10;

    private string $apiUrl;
    private string $apiFileUrl;
    private ZG $tgz;
    private HttpClient $httpClient;

    public function __construct(string $token)
    {
        $this->apiUrl = self::API_BASE_URL . '/bot' . $token . '/';
        $this->apiFileUrl = self::API_BASE_URL . '/file/bot' . $token . '/';
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    public function addZg(ZG $tgz): void
    {
        $this->tgz = $tgz;
    }

    /**
     * @param string $method Метод API
     * @param array|null $params Параметры
     * @param int $timeout Клиентский таймаут <br> По умолчанию: 10.
     */
    public function callAPI(string $method, ?array $params = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $url = $this->apiUrl . $method;
        $body = new Form();

        if ($params) {
            foreach ($params as $key => $value) {
                if ($value instanceof \CURLFile) {
                    $body->addFile($key, $value->getFilename());
                } elseif (is_array($value)) {
                    $body->addField($key, json_encode($value, JSON_THROW_ON_ERROR));
                } else {
                    $body->addField($key, (string)$value);
                }
            }
        }

        $request = new Request($url, 'POST');
        $request->setBody($body);

        $request->setInactivityTimeout($timeout);
        $request->setTransferTimeout($timeout);

        $request->setTcpConnectTimeout(10);
        $request->setTlsHandshakeTimeout(10);

        $response = $this->httpClient->request($request);
        $responseJson = $response->getBody()->read();

        $responseArray = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);

        if ($response->getStatus() === 200 && ($responseArray['ok'] ?? false)) {
            return $responseArray;
        }

        throw new \RuntimeException($this->tgz->TGAPIErrorMSG($responseArray, $params));
    }

    public function getApiUrl(): string { return $this->apiUrl; }
    public function getApiFileUrl(): string { return $this->apiFileUrl; }
}