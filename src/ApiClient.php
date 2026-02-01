<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Form;
use Amp\File;
use ZenithGram\ZenithGram\Exceptions\NetworkException;
use ZenithGram\ZenithGram\Exceptions\TelegramApiException;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;
use ZenithGram\ZenithGram\Utils\LocalFile;

use function Amp\ByteStream\pipe;

class ApiClient implements ApiClientInterface
{

    public const DEFAULT_API_URL = 'https://api.telegram.org';
    private const DEFAULT_TIMEOUT = 10;
    private string $apiUrl;
    private string $apiFileUrl;
    private HttpClient $httpClient;
    private string $token;

    public function __construct(string $token, string $baseUrl = self::DEFAULT_API_URL)
    {
        $baseUrl = rtrim($baseUrl, '/');
        $this->apiUrl = $baseUrl . '/bot' . $token . '/';
        $this->apiFileUrl = $baseUrl . '/file/bot' . $token . '/';
        $this->httpClient = HttpClientBuilder::buildDefault();
        $this->token = $token;
    }

    /**
     * @inheritDoc
     * @throws \Amp\ByteStream\StreamException
     * @throws \Amp\Http\Client\HttpException
     * @throws \JsonException
     * @throws \ZenithGram\ZenithGram\Exceptions\TelegramApiException|\ZenithGram\ZenithGram\Exceptions\NetworkException
     */
    public function callAPI(string $method, array $params = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $url = $this->apiUrl . $method;
        $body = new Form();

        if ($params) {
            foreach ($params as $key => $value) {
                if ($value instanceof LocalFile) {
                    $body->addFile($key, $value->getPath());
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

        try {
            $response = $this->httpClient->request($request);
        } catch (HttpException $e) {
            throw new NetworkException("Ошибка сети при запросе к Telegram API: " . $e->getMessage(), 0, $e);
        }

        $responseJson = $response->getBody()->read();

        try {
            $responseArray = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TelegramApiException("Некорректный JSON ответ от Telegram: " . $e->getMessage());
        }

        if ($response->getStatus() === 200 && ($responseArray['ok'] ?? false)) {
            return $responseArray;
        }

        $errorMsg = sprintf(
            "Ошибка запроса [%s]: %s",
            $responseArray['error_code'] ?? 'Unknown',
            $responseArray['description'] ?? 'No description'
        );

        throw new TelegramApiException($errorMsg . "\n" . $this->formatParamsArray($params), (int)$responseArray['error_code'], $params);
    }

    /**
     * @inheritDoc
     * @throws \Amp\File\FilesystemException
     * @throws \Amp\Http\Client\HttpException
     * @throws \ZenithGram\ZenithGram\Exceptions\TelegramApiException
     * @internal
     */
    public function downloadFile(string $url, string $destinationPath): void
    {
        $request = new Request($url, 'GET');
        $request->setTransferTimeout(300);
        $request->setInactivityTimeout(60);

        $response = $this->httpClient->request($request);

        if ($response->getStatus() !== 200) {
            throw new TelegramApiException("Не удалось скачать файл. HTTP код: " . $response->getStatus());
        }

        $file = File\openFile($destinationPath, 'w');

        try {
            pipe($response->getBody(), $file);
        } finally {
            $file->close();
        }
    }

    /** @internal  */
    public function getApiUrl(): string { return $this->apiUrl; }
    /** @internal  */
    public function getApiFileUrl(): string { return $this->apiFileUrl; }
    /** @internal  */
    public function getToken(): string { return $this->token; }

    private function formatParamsArray(array $array, int $indent = 0): string
    {
        $space = str_repeat(" ", $indent * 2); // символ " " (U+2007, Figure Space)
        $result = "Array (\n";

        foreach ($array as $key => $value) {
            if (is_string($value) && ($decoded = json_decode($value, true)) !== null) {
                $value = $decoded;
            }

            if (is_object($value)) {
                $result .= $space . "  [$key] => " . $this->formatParamsArray((array) $value, $indent + 1);
            }

            if (is_array($value)) {
                $result .= $space . "  [$key] => " . $this->formatParamsArray($value, $indent + 1);
            } else {
                $result .= $space . "  [$key] => " . ($value ?: 'null') . "\n";
            }
        }
        return $result . $space . ")\n";
    }

}

