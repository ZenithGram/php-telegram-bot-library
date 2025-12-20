<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use Closure;
use Throwable;

use function Amp\async;
use function Amp\delay;

class LongPoll
{
    private ApiClient $api;
    private int $timeout;
    private int $offset = 0;
    private bool $skipOldUpdates = false;
    private bool $isRunning = false;

    public function __construct(ApiClient $api, int $timeout = 20)
    {
        $this->api = $api;
        $this->timeout = $timeout;
    }

    public static function create(string $token, int $timeout = 20): self
    {
        return new self(new ApiClient($token), $timeout);
    }

    public function skipOldUpdates(): self
    {
        $this->skipOldUpdates = true;

        return $this;
    }

    public function listen(Closure $handler): void
    {
        $this->isRunning = true;

        // 1. Логика пропуска старых обновлений (Drop pending updates)
        if ($this->skipOldUpdates) {
            $this->dropPendingUpdates();
        }

        // 2. Основной цикл (Event Loop)
        while ($this->isRunning) {
            try {
                $updates = $this->fetchUpdates();

                if (empty($updates)) {
                    continue;
                }

                foreach ($updates as $updateData) {
                    // Обновляем offset СРАЗУ, чтобы в следующем запросе не получить это же сообщение,
                    // даже если текущее еще обрабатывается.
                    $this->offset = $updateData['update_id'] + 1;

                    // МАГИЯ AMP: async() запускает код в отдельном файбере.
                    // Мы НЕ ждем завершения handler($tg), мы сразу переходим к следующему обновлению.
                    async(function() use ($handler, $updateData) {
                        try {
                            $this->processUpdate($handler, $updateData);
                        } catch (Throwable $e) {
                            // Логируем ошибку конкретного апдейта, но не роняем бота
                            // Тут можно подключить Logger, пока просто вывод в stderr
                            fwrite(
                                STDERR,
                                "[Update Error] ".$e->getMessage().PHP_EOL,
                            );
                        }
                    });
                }

            } catch (Throwable $e) {
                // Ошибка уровня сети или API (например, Telegram упал)
                // Делаем паузу, чтобы не долбить API в цикле ошибок
                fwrite(STDERR, "[Network Error] ".$e->getMessage().PHP_EOL);
                delay(2);
            }
        }
    }

    private function fetchUpdates(): array
    {
        $clientTimeout = $this->timeout + 15;

        $response = $this->api->callAPI(
            'getUpdates',
            [
                'offset'          => $this->offset,
                'timeout'         => $this->timeout,
                'allowed_updates' => [], // Получаем всё
            ],
            $clientTimeout,
        );

        return $response['result'] ?? [];
    }

    private function dropPendingUpdates(): void
    {
        try {
            $data = $this->api->callAPI(
                'getUpdates', ['limit' => 1, 'offset' => -1],
            );
            if (!empty($data['result'])) {
                $lastUpdate = end($data['result']);
                $this->offset = $lastUpdate['update_id'] + 1;
            }
        } catch (Throwable $e) {
            // Игнорируем ошибки при пропуске, просто начинаем работу
        }
    }

    private function processUpdate(Closure $handler, array $updateData): void
    {
        // Создаем ИЗОЛИРОВАННЫЙ контекст для конкретного апдейта
        $context = new UpdateContext($updateData);

        // ВАЖНО: Создаем новый экземпляр ZG для каждого запроса.
        // Это предотвращает состояние гонки (Race Condition), когда
        // данные одного юзера перезаписывают данные другого.
        $tgInstance = new ZG($this->api, $context);

        // Внедряем зависимость обратно в API (для обработки ошибок)
        // Примечание: Это место архитектурно слабое в оригинальной библиотеке (stateful service),
        // но мы его пока сохраняем для совместимости.
        $this->api->addZg($tgInstance);

        // Запускаем пользовательскую логику
        $handler($tgInstance);
    }
}