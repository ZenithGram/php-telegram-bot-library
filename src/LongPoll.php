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
    private bool $shortTrace = false;
    private string $pathFiler = '';
    private array|null $debug_chat_ids = null;
    private bool $debug = false;
    private Closure|null $handler = null;
    private array $allowedUpdates = [];

    public function __construct(ApiClient $api, int $timeout = 20)
    {
        $this->api = $api;
        $this->timeout = $timeout;
    }

    /**
     * Создает объект класса LongPoll
     *
     * @param string $token   Токен Telegram
     * @param int    $timeout Сколько будет удерживаться соединение с Telegram
     * @param string $baseUrl Адрес локального сервера Telegram (По умолчанию:
     *                        https://api.telegram.org)
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/longpoll
     */
    public static function create(string $token, int $timeout = 20,
        string $baseUrl = ApiClient::DEFAULT_API_URL,
    ): self {
        return new self(new ApiClient($token, $baseUrl), $timeout);
    }

    /**
     * Устанавливает типы обновлений, которые бот хочет получать.
     *
     * @param array $updates Массив строк или Enum значений UpdateType.
     *                       Пример: [UpdateType::Message, 'callback_query']
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/longpoll#allowedupdates
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
     * Пропускает старые обновления
     *
     * @return LongPoll
     * @see https://zenithgram.github.io/classes/longpoll#skipoldupdates
     */
    public function skipOldUpdates(): self
    {
        $this->skipOldUpdates = true;

        return $this;
    }

    /**
     * Запускает опрос серверов Telegram
     *
     * @param Closure $handler
     *
     * @return void
     * @see https://zenithgram.github.io/classes/longpoll#listen
     */
    public function listen(Closure $handler): void
    {
        $isRunning = true;

        if ($this->skipOldUpdates) {
            $this->dropPendingUpdates();
        }

        while ($isRunning) {
            try {
                $updates = $this->fetchUpdates();

                if (empty($updates)) {
                    continue;
                }

                foreach ($updates as $updateData) {
                    $this->offset = $updateData['update_id'] + 1;

                    async(function() use ($handler, $updateData) {
                        try {
                            $this->processUpdate($handler, $updateData);
                        } catch (Throwable $e) {
                            $this->reportInternalError($e, 'Update Error');
                        }
                    });
                }

            } catch (Throwable $e) {
                $this->reportInternalError($e, 'Network Error');
                delay(2);
            }
        }
    }

    private function reportInternalError(Throwable $e, $error): void
    {
        $context = new UpdateContext([]);
        $tgInstance = new ZG($this->api, $context);

        if ($this->debug) {
            $tgInstance
                ->enableDebug()
                ->shortTrace($this->shortTrace)
                ->setTracePathFilter($this->pathFiler);

            if ($this->handler !== null) {
                $tgInstance->setHandler($this->handler);
            }

            if ($this->debug_chat_ids !== null) {
                $tgInstance->setSendIds($this->debug_chat_ids);
            }

            $tgInstance->reportException($e);
        } else {
            fwrite(STDERR, "[$error] ".$e->getMessage().PHP_EOL);
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
                'getUpdates', ['limit' => 1, 'offset' => -1,],
            );
            if (!empty($data['result'])) {
                $lastUpdate = end($data['result']);
                $this->offset = $lastUpdate['update_id'] + 1;
            }
        } catch (Throwable $e) {
        }
    }

    private function processUpdate(Closure $handler, array $updateData): void
    {
        $context = new UpdateContext($updateData);

        $tgInstance = new ZG($this->api, $context);
        if ($this->debug) {
            $tgInstance
                ->enableDebug()
                ->shortTrace($this->shortTrace)
                ->setTracePathFilter($this->pathFiler);
            if ($this->handler !== null) {
                $tgInstance->setHandler($this->handler);
            }
            if ($this->debug_chat_ids !== null) {
                $tgInstance->setSendIds($this->debug_chat_ids);
            }
        }
        try {
            $handler($tgInstance);
        } catch (Throwable $e) {
            $tgInstance->reportException($e);
        }
    }

    /**
     * Активирует дебаг режим
     *
     * Чтобы он работал, нужно задать обработчик, либо перечислить id, куда
     * будет отправлены ошибки
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/errorhandler#enableDebug
     */
    public function enableDebug(): self
    {
        $this->debug = true;

        return $this;
    }

    /**
     * Устанавливает ID, куда будут отправлены возникшие ошибки
     *
     * @param int|string|array $ids ID пользователя или чата
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setSendIds
     */
    public function setSendIds(int|string|array $ids): self
    {
        $this->debug_chat_ids = is_array($ids) ? $ids : [$ids];

        return $this;
    }

    /**
     * Устанавливает обработчик, который срабатывает при возникновении ошибки
     *
     * @param callable $handler Обработчик. Пример: function (ZG $tg, Throwable
     *                          $e)
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setHandler
     */
    public function setHandler(callable $handler): self
    {
        $this->handler = $handler(...);

        return $this;
    }

    /**
     * Отображать полный трейт или сокращенный
     *
     * @param bool $short
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/errorhandler#shortTrace
     */
    public function shortTrace(bool $short = true): self
    {
        $this->shortTrace = $short;

        return $this;
    }

    /**
     * Устанавливает фильтр на путь к файлу, чтобы не отображать его
     *
     * @param string $filter "/path/to/your/project"
     *
     * @return LongPoll
     *
     * @see https://zenithgram.github.io/classes/errorhandler#setTracePathFilter
     */
    public function setTracePathFilter(string $filter): self
    {
        $this->pathFiler = $filter;

        return $this;
    }
}