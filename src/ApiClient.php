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
    private string $apiUrl;
    private string $apiFileUrl;
    private ZG $tgz;
    private HttpClient $httpClient;

    public function __construct(string $token)
    {
        $this->apiUrl = self::API_BASE_URL . '/bot' . $token . '/';
        $this->apiFileUrl = self::API_BASE_URL . '/file/bot' . $token . '/';

        // HttpClientBuilder создает клиент, который умеет работать асинхронно
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    public function addZg(ZG $tgz): void
    {
        $this->tgz = $tgz;
    }

    public function callAPI(string $method, ?array $params = []): array
    {
        $url = $this->apiUrl . $method;

        $body = new Form();

        if ($params) {
            foreach ($params as $key => $value) {
                if ($value instanceof \CURLFile) {
                    // Метод addFile в твоем файле Form.php есть на строке 82
                    $body->addFile($key, $value->getFilename());
                } elseif (is_array($value)) {
                    // Данные кнопок или массивов превращаем в JSON
                    $body->addField($key, json_encode($value, JSON_THROW_ON_ERROR));
                } else {
                    // Обычные текстовые поля (строка 45 в твоем файле)
                    $body->addField($key, (string)$value);
                }
            }
        }

        $request = new Request($url, 'POST');
        $request->setBody($body);

        // Выполняем запрос
        $response = $this->httpClient->request($request);

        // Читаем ответ. В v5 метод read() возвращает строку целиком.
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