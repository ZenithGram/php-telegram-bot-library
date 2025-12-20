<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Form;
use Amp\File;
use function Amp\ByteStream\pipe;

class ApiClient
{
    private const API_BASE_URL = 'https://api.telegram.org';
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

    /**
     * Асинхронно скачивает файл по ссылке и сохраняет его на диск
     */
    public function downloadFile(string $url, string $destinationPath): void
    {
        // 1. Делаем запрос (GET)
        $request = new Request($url, 'GET');
        // Таймаут побольше для скачивания (5 минут)
        $request->setTransferTimeout(300);
        $request->setInactivityTimeout(60);

        $response = $this->httpClient->request($request);

        if ($response->getStatus() !== 200) {
            throw new \RuntimeException("Не удалось скачать файл. HTTP код: " . $response->getStatus());
        }

        // 2. Открываем файл на диске для записи (асинхронно)
        // Если папки нет, она должна быть создана ДО вызова этого метода
        $file = File\openFile($destinationPath, 'w');

        try {
            // 3. "Труба": переливаем данные из сети (Body) в файл на диске
            // Это не забивает оперативную память и не блокирует поток
            pipe($response->getBody(), $file);
        } finally {
            // 4. Обязательно закрываем файл
            $file->close();
        }
    }

    public function getApiUrl(): string { return $this->apiUrl; }
    public function getApiFileUrl(): string { return $this->apiFileUrl; }
}