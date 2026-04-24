<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Form;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Tunnel\Http1TunnelConnector;
use Amp\File as AmpFile;
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

    public function __construct(string $token, string $baseUrl = self::DEFAULT_API_URL, ?string $proxyUrl = null)
    {
        $baseUrl = rtrim($baseUrl, '/');
        $this->apiUrl = $baseUrl . '/bot' . $token . '/';
        $this->apiFileUrl = $baseUrl . '/file/bot' . $token . '/';

        $builder = new HttpClientBuilder();

        if ($proxyUrl !== null) {
            $connector = new Http1TunnelConnector($proxyUrl);

            $connectionFactory = new DefaultConnectionFactory($connector);

            $pool = new UnlimitedConnectionPool($connectionFactory);

            $builder = $builder->usingPool($pool);
        }

        $this->httpClient = $builder->build();


        $this->token = $token;
    }

    /**
     * @inheritDoc
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
        } catch (\Amp\Http\Client\SocketException $e) {
            throw new NetworkException("Не удалось установить сетевое соединение: " . $e->getMessage(), 0, $e);
        } catch (\Amp\Http\Client\TimeoutException $e) {
            throw new NetworkException("Превышен таймаут ожидания ответа от Telegram API: " . $e->getMessage(), 0, $e);
        } catch (\Amp\Http\Client\HttpException $e) {
            throw new NetworkException("Ошибка HTTP-клиента при выполнении запроса: " . $e->getMessage(), 0, $e);
        }

        try {
            $responseJson = $response->getBody()->buffer();
        } catch (\Amp\ByteStream\StreamException $e) {
            throw new TelegramApiException("Внезапный обрыв соединения при загрузке ответа от Telegram: " . $e->getMessage());
        } catch (\Throwable $e) {
            throw new TelegramApiException("Неизвестная ошибка при чтении потока: " . $e->getMessage());
        }

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
    /**
     * @inheritDoc
     * @throws \Amp\File\FilesystemException
     * @throws \ZenithGram\ZenithGram\Exceptions\TelegramApiException
     * @throws \ZenithGram\ZenithGram\Exceptions\NetworkException
     * @internal
     */
    public function downloadFile(string $url, string $destinationPath): void
    {
        $request = new Request($url, 'GET');
        $request->setTransferTimeout(300);
        $request->setInactivityTimeout(60);

        $request->setTcpConnectTimeout(10);
        $request->setTlsHandshakeTimeout(10);

        try {
            $response = $this->httpClient->request($request);
        } catch (\Amp\Http\Client\SocketException $e) {
            throw new NetworkException("Не удалось подключиться к серверу для скачивания файла: " . $e->getMessage(), 0, $e);
        } catch (\Amp\Http\Client\TimeoutException $e) {
            throw new NetworkException("Таймаут при попытке начать скачивание файла: " . $e->getMessage(), 0, $e);
        } catch (\Amp\Http\Client\HttpException $e) {
            throw new NetworkException("Сетевая ошибка при запросе на скачивание файла: " . $e->getMessage(), 0, $e);
        }

        if ($response->getStatus() !== 200) {
            throw new TelegramApiException("Не удалось скачать файл. HTTP код: " . $response->getStatus());
        }

        $file = AmpFile\openFile($destinationPath, 'w');

        try {
            pipe($response->getBody(), $file);
        } catch (\Throwable $e) {
            if ($e instanceof \Amp\File\FilesystemException) {
                throw new TelegramApiException("Ошибка файловой системы при сохранении файла: " . $e->getMessage(), );
            }

            throw new NetworkException("Обрыв соединения во время скачивания файла: " . $e->getMessage(), 0, $e);

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

