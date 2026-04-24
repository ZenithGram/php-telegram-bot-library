<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Closure;
use Throwable;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;
use ZenithGram\ZenithGram\Enums\UpdateType;
use function Amp\async;
use function Amp\delay;

class LongPoll
{
    private int $offset = 0;
    private bool $skipOldUpdates = false;
    private array $allowedUpdates = [];

    public function __construct(
        public readonly ApiClientInterface $api,
        private readonly int $timeout = 20,
    ) {}

    /**
     * Статический метод-фабрика для создания LongPoll.
     *
     * @param string $token Токен бота
     * @param int $timeout Таймаут соединения (сек)
     * @param string $baseUrl URL API Telegram
     * @param string|null $proxyUrl Прокси (опционально)
     */
    public static function create(
        string $token,
        int $timeout = 20,
        string $baseUrl = ApiClient::DEFAULT_API_URL,
        ?string $proxyUrl = null
    ): self {
        return new self(new ApiClient($token, $baseUrl, $proxyUrl), $timeout);
    }

    /**
     * Устанавливает типы обновлений, которые бот хочет получать.
     */
    public function allowedUpdates(array $updates): self
    {
        $this->allowedUpdates = array_map(function($item) {
            if ($item instanceof UpdateType) {
                return $item->value;
            }
            return (string)$item;
        }, $updates);
        return $this;
    }

    /**
     * Пропускает все накопленные ранее обновления при старте.
     */
    public function skipOldUpdates(): self
    {
        $this->skipOldUpdates = true;
        return $this;
    }

    /**
     * Запускает бесконечный цикл опроса серверов Telegram.
     */
    public function listen(Closure $handler): void
    {
        if ($this->skipOldUpdates) {
            $this->dropPendingUpdates();
        }

        while (true) {
            try {
                $updates = $this->fetchUpdates();

                if (empty($updates)) {
                    continue;
                }

                foreach ($updates as $updateData) {
                    $this->offset = $updateData['update_id'] + 1;

                    async(function() use ($handler, $updateData) {
                        $this->processUpdate($handler, $updateData);
                    });
                }
            } catch (Throwable $e) {
                $this->reportInternalError($e);
                delay(2);
            }
        }
    }

    /**
     * Обрабатывает конкретный апдейт и изолирует ошибки в нем.
     */
    private function processUpdate(Closure $handler, array $updateData): void
    {
        $context = new UpdateContext($updateData);
        $tgInstance = new ZG($this->api, $context);

        try {
            $handler($tgInstance);
        } catch (Throwable $e) {
            ErrorHandler::catch($e, $tgInstance);
        }
    }

    private function reportInternalError(Throwable $e): void
    {
        ErrorHandler::catch($e);
    }

    private function fetchUpdates(): array
    {
        $clientTimeout = $this->timeout + 15;
        $response = $this->api->callAPI(
            'getUpdates',
            [
                'offset'          => $this->offset,
                'timeout'         => $this->timeout,
                'allowed_updates' => $this->allowedUpdates,
            ],
            $clientTimeout,
        );
        return $response['result'] ?? [];
    }

    private function dropPendingUpdates(): void
    {
        try {
            $data = $this->api->callAPI(
                'getUpdates', ['limit' => 1, 'offset' => -1]
            );
            if (!empty($data['result'])) {
                $lastUpdate = end($data['result']);
                $this->offset = $lastUpdate['update_id'] + 1;
            }
        } catch (Throwable) {}
    }
}