<?php

namespace ZenithGram\ZenithGram;

use Exception;
use LogicException;
use ZenithGram\ZenithGram\Dto\ChatDto;
use ZenithGram\ZenithGram\Dto\MessageDto;
use ZenithGram\ZenithGram\Dto\UserDto;
use ZenithGram\ZenithGram\Enums\MessageParseMode;
use ZenithGram\ZenithGram\Enums\ChatAction;
use ZenithGram\ZenithGram\Utils\EnvironmentDetector;
use ZenithGram\ZenithGram\Storage\StorageInterface;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;

class ZG
{
    use ErrorHandler;

    public ApiClientInterface $api;
    public UpdateContext $context;
    public MessageParseMode $parseModeDefault = MessageParseMode::None;
    private ?StorageInterface $storage = null;

    public function __construct(ApiClientInterface $api, UpdateContext $context)
    {
        $this->api = $api;
        $this->context = $context;
    }

    /**
     * Создает объект класса ZG
     *
     * @param string $token   Токен, полученный в BotFather
     * @param string $baseUrl Адрес локального сервера Telegram (По умолчанию: https://api.telegram.org)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/zenith
     */
    public static function create(string $token, string $baseUrl = ApiClient::DEFAULT_API_URL): self
    {
        $api = new ApiClient($token, $baseUrl);
        $context = UpdateContext::fromWebhook();

        return new self($api, $context);
    }

    /**
     * Выполняет вызов к Telegram Bot API.
     *
     * @param string     $method
     * @param array|null $params
     *
     * @return array
     *
     * @throws Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/callAPI
     */
    public function callAPI(string $method, ?array $params = []): array
    {
        return $this->api->callAPI($method, $params);
    }

    /**
     * Устанавливает режим парсинга по умолчанию для всех сообщений
     *
     * @param MessageParseMode $mode
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/defaultParseMode
     */
    public function defaultParseMode(MessageParseMode $mode): self
    {
        $this->parseModeDefault = $mode;

        return $this;
    }

    /**
     * Явно отправляет 200 OK Telegram'у.
     * Вызывается автоматически в Bot::run() или вручную.
     *
     * @return void
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/sendOk
     */
    public function sendOk(): void
    {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/plain');
        }

        echo 'ok';

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Метод создает объект класса Message для конструктора сообщений
     *
     * @param string $text
     *
     * @return Message
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/msg
     */
    public function msg(string $text = ''): Message
    {
        return new Message(
            $text, $this,
        );
    }

    /**
     * Метод создает объект класса Poll для конструктора опросов
     *
     * @param string $type
     *
     * @return Poll
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/poll
     */
    public function poll(string $type = 'regular'): Poll
    {
        return new Poll($type, $this->api, $this->context);
    }

    /**
     * Метод создает объект класса Inline для конструктора Inline-запросов
     *
     * @param string $type
     *
     * @return Inline
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/inline
     */
    public function inline(string $type = ''): Inline
    {
        return new Inline($type, $this->parseModeDefault);
    }

    /**
     * Метод создает объект класса Pagination для конструктора страниц
     *
     * @return \ZenithGram\ZenithGram\Pagination
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/pagination
     */
    public function pagination(): Pagination
    {
        return new Pagination();
    }

    /**
     * Метод создает объект класса File для скачивания
     *
     * @param string $file_id
     *
     * @return File
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/file
     */
    public function file(string $file_id): File
    {
        return new File($file_id, $this->api);
    }

    /**
     * Устанавливает активное хранилище состояний (FSM)
     *
     * @param StorageInterface $storage Объект хранилища
     *
     * @return ZG
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/setStorage
     */
    public function setStorage(StorageInterface $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Возвращает экземпляр текущего хранилища для прямых манипуляций
     *
     * @return StorageInterface|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/storage
     */
    public function getStorage(): ?StorageInterface
    {
        return $this->storage;
    }

    /**
     * Переводит пользователя на следующий шаг (состояние)
     *
     * @param string $state Название следующего состояния
     *
     * @return void
     *
     * @throws LogicException|\JsonException Если хранилище не подключено
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/step
     */
    public function step(string $state): void
    {
        if (!$this->storage) {
            throw new LogicException(
                'Storage is not configured. Use Bot->setStorage().',
            );
        }
        $userId = $this->getUserId();
        if ($userId) {
            $this->storage->setState($userId, $state);
        }
    }

    /**
     * Завершает текущий шаг (сбрасывает состояние)
     *
     * Опционально можно задать удаление всех данных сессии
     *
     * @param bool $clear_data Удалять все данные сессии True/False
     *
     * @return void
     *
     * @throws LogicException|\JsonException Если хранилище не подключено
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/endStep
     */
    public function endStep(bool $clear_data = true): void
    {
        if (!$this->storage) {
            throw new LogicException('Storage is not configured.');
        }
        $user_id = $this->getUserId();
        if ($user_id) {
            $this->storage->clearState($user_id);
            if ($clear_data) {
                $this->storage->clearSessionData($user_id);
            }
        }
    }

    /**
     * Универсальный метод для работы с данными сессии
     *
     * Если передан массив $data — сохраняет (мержит) данные.
     * Если $data не передан — возвращает текущие накопленные данные.
     *
     * @param array|null $data Данные для сохранения (опционально)
     *
     * @return array Текущие данные сессии
     *
     * @throws LogicException Если хранилище не подключено
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/session
     */
    public function session(?array $data = null): array
    {
        if (!$this->storage) {
            throw new LogicException('Storage is not configured.');
        }

        $user_id = $this->getUserId();
        if (!$user_id) {
            return [];
        }

        if ($data !== null) {
            $this->storage->setSessionData($user_id, $data);

            return $this->storage->getSessionData($user_id);
        }

        return $this->storage->getSessionData($user_id);
    }

    /**
     * Метод удаляет одно или несколько сообщений
     *
     * @param array|int|null  $msg_ids
     * @param int|string|null $chat_id
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/delMsg
     */
    public function delMsg(array|int|null $msg_ids = null,
        int|string|null $chat_id = null,
    ): array {
        $msg_ids ??= $this->context->getMessageId();
        $chat_id ??= $this->context->getChatId();

        if (is_array($msg_ids)) {
            $method = 'deleteMessages';
            $params = ['message_ids' => $msg_ids];
        } else {
            $method = 'deleteMessage';
            $params = ['message_id' => $msg_ids];
        }

        $params['chat_id'] = $chat_id;

        return $this->api->callAPI(
            $method, $params,
        );
    }

    /**
     * Метод копирует одно или несколько сообщений
     *
     * @param int|array|null  $msg_ids      ID сообщения или массив ID
     *                                      сообщений
     * @param int|string|null $chat_id      Куда пересылать (по умолчанию
     *                                      текущий чат)
     * @param int|string|null $from_chat_id Откуда пересылать (по умолчанию из
     *                                      chat_id)
     * @param array           $params       Дополнительные параметры (caption,
     *                                      parse_mode, reply_markup,
     *                                      message_thread_id)
     *
     * @return array
     *
     * @throws \Amp\Http\Client\HttpException
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/copyMsg
     * @see https://core.telegram.org/bots/api#copyMessages
     * @see https://core.telegram.org/bots/api#copyMessage
     */
    public function copyMsg(int|array|null $msg_ids = null,
        int|string|null $chat_id = null, int|string|null $from_chat_id = null,
        array $params = [],
    ): array {
        $msg_ids ??= $this->context->getMessageId();
        $chat_id ??= $this->context->getChatId();
        $from_chat_id ??= $chat_id;

        $isArray = is_array($msg_ids);

        if ($isArray) {
            $method = 'copyMessages';
            $baseParams = ['message_ids' => $msg_ids];
        } else {
            $method = 'copyMessage';
            $baseParams = ['message_id' => $msg_ids];
        }

        $baseParams['chat_id'] = $chat_id;
        $baseParams['from_chat_id'] = $from_chat_id;

        $payload = array_merge($baseParams, $params);

        return $this->api->callAPI($method, $payload);
    }

    /**
     * Метод пересылает одно или несколько сообщений
     *
     * @param int|array|null  $msg_ids      ID сообщения или массив ID
     *                                      сообщений
     * @param int|string|null $chat_id      Куда пересылать (по умолчанию
     *                                      текущий чат)
     * @param int|string|null $from_chat_id Откуда пересылать (по умолчанию из
     *                                      chat_id)
     * @param array           $params       Доп. параметры (message_thread_id,
     *                                      disable_notification,
     *                                      protect_content)
     *
     * @return array
     *
     * @throws \Amp\Http\Client\HttpException
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/fwdMsg
     * @see https://core.telegram.org/bots/api#forwardmessages
     * @see https://core.telegram.org/bots/api#forwardmessage
     */
    public function fwdMsg(
        int|array|null $msg_ids = null,
        int|string|null $chat_id = null,
        int|string|null $from_chat_id = null,
        array $params = [],
    ): array {
        $msg_ids ??= $this->context->getMessageId();
        $chat_id ??= $this->context->getChatId();
        $from_chat_id ??= $chat_id;

        if (is_array($msg_ids)) {
            $method = 'forwardMessages';
            $baseParams = ['message_ids' => $msg_ids];
        } else {
            $method = 'forwardMessage';
            $baseParams = ['message_id' => $msg_ids];
        }

        $baseParams['chat_id'] = $chat_id;
        $baseParams['from_chat_id'] = $from_chat_id;

        $payload = array_merge($baseParams, $params);

        return $this->api->callAPI($method, $payload);
    }

    /**
     * Закрепляет сообщение в чате
     *
     * @param int|null        $msg_id               ID сообщения, которое будет
     *                                              закреплено
     * @param int|string|null $chat_id              ID чата, в котором будет
     *                                              закреплено сообщение
     * @param bool            $disable_notification Выключить звук? True/False
     *
     * @return array
     *
     * @throws \Amp\Http\Client\HttpException
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/pinMsg
     */
    public function pinMsg(
        int|null $msg_id = null,
        int|string|null $chat_id = null,
        bool $disable_notification = false,
    ): array {
        $msg_id ??= $this->context->getMessageId();
        $chat_id ??= $this->context->getChatId();

        return $this->api->callAPI('pinChatMessage', [
            'chat_id'              => $chat_id,
            'message_id'           => $msg_id,
            'disable_notification' => $disable_notification,
        ]);
    }

    /**
     * Открепляет сообщение
     *
     * @param int|null        $msg_id  ID сообщения, которое будет
     *                                 закреплено
     * @param int|string|null $chat_id ID чата, в котором будет
     *                                 закреплено сообщение
     * @param bool            $all     Открепить все сообщения? True/False
     *
     * @return array
     *
     * @throws \Amp\Http\Client\HttpException
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/unpinMsg
     */
    public function unpinMsg(
        int|null $msg_id = null,
        int|string|null $chat_id = null,
        bool $all = false,
    ): array {
        $chat_id ??= $this->context->getChatId();

        $params = ['chat_id' => $chat_id];

        if ($all) {
            $method = 'unpinAllChatMessage';
        } else {
            $msg_id ??= $this->context->getMessageId();
            $params['message_id'] = $msg_id;
            $method = 'unpinChatMessage';
        }

        return $this->api->callAPI($method, $params);
    }

    /**
     * Устанавливает действие бота
     *
     * @param ChatAction $action Одно из действий перечисления (Enum)
     *
     *                           По умолчанию: Typing
     *
     * @return ZG
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/sendAction
     */
    public function sendAction(ChatAction $action = ChatAction::Typing,
    ): self {
        $this->api->callAPI(
            'sendChatAction',
            ['chat_id' => $this->context->getChatId(),
             'action'  => $action->value,
            ],
        );

        return $this;
    }

    /**
     * Метод отправляет сообщение в чат
     *
     * @param int|string $chat_id
     * @param string     $text
     * @param array      $params
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/sendMessage
     */
    public function sendMessage(int|string $chat_id, string $text,
        array $params = [],
    ): array {
        $params_message = [
            'chat_id' => $chat_id,
            'text'    => $text,
        ];

        return $this->api->callAPI(
            'sendMessage', $params_message + $params,
        );
    }

    /**
     * Метод отправляет сообщение в чат
     *
     * @param string $text
     * @param array  $params
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/reply
     */
    public function reply(string $text, array $params = []): array
    {
        if (!isset($params['chat_id'])) {
            $params['chat_id'] = $this->context->getChatId();
        }

        return $this->api->callAPI(
            'sendMessage', array_merge($params, ['text' => $text]),
        );
    }

    /**
     * Создает callback-кнопку
     *
     * Устаревший алиас метода Keyboard::cb()
     *
     * @param string $buttonText Текст кнопки
     * @param string $buttonData Данные кнопки
     *
     * @return array
     */
    public function buttonCallback(string $buttonText, string $buttonData,
    ): array {
        return Button::cb($buttonText, $buttonData);
    }

    /**
     * Создает url-кнопку
     *
     * Устаревший алиас метода Keyboard::url()
     *
     * @param string $buttonText Текст кнопки
     * @param string $buttonUrl  URL
     *
     * @return array
     */
    public function buttonUrl(string $buttonText, string $buttonUrl): array
    {
        return Button::url($buttonText, $buttonUrl);
    }

    /**
     * Создает текстовую кнопку
     *
     * Устаревший алиас метода Keyboard::text()
     *
     * @param string $buttonText Текст кнопки
     *
     * @return array
     */
    public function buttonText(string $buttonText): array
    {
        return Button::text($buttonText);
    }

    /**
     * Метод отправляет ответ Телеграму на callback-запрос
     *
     * @param string|array|null $queryIdOrParams Можно указать ID
     *                                           (ZG::getQueryId), или сразу
     *                                           параметры типа ['text'
     *                                           => 'Всплывающее сообщение']
     * @param array             $params          Если в первом параметре указан
     *                                           ID, то параметры можно указать
     *                                           вторым аргументом
     *
     * @return array
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/answers#answercallbackquery
     */
    public function answerCallbackQuery(string|array|null $queryIdOrParams = null,
        array $params = [],
    ): array {
        if (is_array($queryIdOrParams) || $queryIdOrParams === null) {
            $queryId = $this->context->getQueryId();
            $params = array_merge($params, $queryIdOrParams ?? []);
        } else {
            $queryId = $queryIdOrParams;
        }

        $params_methods = array_merge([
            'callback_query_id' => $queryId,
        ], $params);

        return $this->api->callAPI('answerCallbackQuery', $params_methods);
    }

    /**
     * Метод отправляет ответ Телеграму на inline-запрос
     *
     * @param string|array $queryIdOrResults ID запроса (string) или сразу
     *                                       массив результатов (array), тогда
     *                                       ID берется из контекста.
     * @param array        $resultsOrParams  Если 1-й аргумент ID, то здесь
     *                                       результаты. Если 1-й — результаты,
     *                                       то здесь доп. параметры (extra).
     * @param array        $extra            Доп. параметры (если указаны ID и
     *                                       результаты первыми двумя
     *                                       аргументами).
     *
     * @return array
     * @throws \JsonException
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/answers#answerinlinequery
     */
    public function answerInlineQuery(string|array $queryIdOrResults,
        array $resultsOrParams = [], array $extra = [],
    ): array {
        if (is_array($queryIdOrResults)) {
            $queryId = $this->context->getQueryId();
            $results = $queryIdOrResults;
            $params = $resultsOrParams;
        } else {
            $queryId = $queryIdOrResults;
            $results = $resultsOrParams;
            $params = $extra;
        }

        $basePayload = [
            'inline_query_id' => $queryId,
            'results'         => json_encode($results, JSON_THROW_ON_ERROR),
        ];

        return $this->api->callAPI(
            'answerInlineQuery', array_merge($basePayload, $params),
        );
    }

    /**
     * Инициализирует переменные из обновления
     *
     * @param $chat_id
     * @param $user_id
     * @param $text
     * @param $type
     * @param $callback_data
     * @param $query_id
     * @param $msg_id
     * @param $is_bot
     * @param $is_command
     *
     * @return array
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/initVars
     */
    public function initVars(
        &$chat_id = null,
        &$user_id = null,
        &$text = null,
        &$type = null,
        &$callback_data = null,
        &$query_id = null,
        &$msg_id = null,
        &$is_bot = null,
        &$is_command = null,
    ): array {
        $update = $this->context->getUpdateData();

        $user_id = $this->context->getUserId();
        $chat_id = $this->context->getChatId();
        $text = $this->context->getText();
        $msg_id = $this->context->getMessageId();
        $type = $this->context->getType();
        $query_id = $this->context->getQueryId();
        $callback_data = $this->context->getCallbackData();

        if (isset($update['message'])) {
            $is_bot = $update['message']['from']['is_bot'];

            $is_command = (isset($update['message']['entities'][0]['type'])
                && $update['message']['entities'][0]['type'] === 'bot_command');

        } elseif (isset($update['callback_query'])) {
            $is_bot = $update['callback_query']['from']['is_bot'];

            $is_command = false;

        } elseif (isset($update['inline_query'])) {
            $is_bot = $update['inline_query']['from']['is_bot'];

            $is_command = false;

        }

        return $update;
    }

    /**
     * Возвращает данные обновления
     *
     * @return array
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getupdate
     */
    public function getUpdate(): array
    {
        return $this->context->getUpdateData();
    }

    /**
     * Возвращает переменную callback_data
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getcallbackdata
     */
    public function getCallbackData(): string|null
    {
        return $this->context->getCallbackData();
    }

    /**
     * Возвращает переменную query_id
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getqueryid
     */
    public function getQueryId(): string|null
    {
        return $this->context->getQueryId();
    }

    /**
     * Возвращает переменную type
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#gettype
     */
    public function getType(): string|null
    {
        return $this->context->getType();
    }

    /**
     * Возвращает переменную msg_id
     *
     * @return int|string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getmsgid
     */
    public function getMsgId(): int|string|null
    {
        return $this->context->getMessageId();
    }

    /**
     * Возвращает переменную msg_id из отвеченного сообщения
     *
     * @return int|string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getreplymsgid
     */
    public function getReplyMsgId(): int|string|null
    {
        return $this->context->getReplyMessageId();
    }

    /**
     * Возвращает переменную text
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#gettext
     */
    public function getText(): string|null
    {
        return $this->context->getText();
    }

    /**
     * Возвращает переменную text из отвеченного сообщения
     *
     * @return string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getreplytext
     */
    public function getReplyText(): string|null
    {
        return $this->context->getReplyText();
    }

    /**
     * Возвращает переменную user_id
     *
     * @return int|string|null user_id
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getuserid
     */
    public function getUserId(): int|string|null
    {
        return $this->context->getUserId();
    }

    /**
     * Возвращает переменную user_id из отвеченного сообщения
     *
     * @return int|string|null user_id
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getreplyuserid
     */
    public function getReplyUserId(): int|string|null
    {
        return $this->context->getReplyUserId();
    }

    /**
     * Возвращает переменную chat_id
     *
     * @return int|string|null
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getchatid
     */
    public function getChatId(): int|string|null
    {
        return $this->context->getChatId();
    }

    /**
     * Извлекает данные пользователя из любого подходящего поля в текущем
     * событии.
     *
     * Этот метод универсален и ищет данные пользователя ('from') в таких
     * событиях, как message, callback_query, inline_query, my_chat_member и
     * других.
     *
     * @return UserDto Объект пользователя.
     * @throws LogicException Если данные пользователя не найдены в событии.
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getuser
     */
    public function getUser(): UserDto
    {
        $update = $this->context->getUpdateData();
        $user = null;

        $keys = [
            'message',
            'edited_message',
            'callback_query',
            'inline_query',
            'my_chat_member',
            'chat_member',
            'chat_join_request',
        ];

        foreach ($keys as $key) {
            if (isset($update[$key]['from'])) {
                $user = $update[$key]['from'];
                break;
            }
        }

        if ($user === null) {
            throw new LogicException(
                "Не удалось найти данные пользователя ('from') в текущем событии.",
            );
        }

        return UserDto::fromArray($user);
    }

    /**
     * Извлекает данные чата из любого подходящего поля в текущем событии.
     *
     * Этот метод универсален и ищет данные чата ('chat') в таких событиях,
     * как message, callback_query, channel_post, my_chat_member и других,
     * корректно обрабатывая вложенные структуры.
     *
     * @return ChatDto Объект чата.
     * @throws LogicException Если данные чата не найдены в событии.
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getchat
     */
    public function getChat(): ChatDto
    {
        $update = $this->context->getUpdateData();
        $chatData = null;

        $paths = [
            ['message', 'chat'],
            ['edited_message', 'chat'],
            ['channel_post', 'chat'],
            ['edited_channel_post', 'chat'],
            ['my_chat_member', 'chat'],
            ['chat_member', 'chat'],
            ['chat_join_request', 'chat'],
            ['callback_query', 'message', 'chat'],
        ];

        foreach ($paths as $path) {
            $temp = $update;
            $found = true;

            foreach ($path as $key) {
                if (!isset($temp[$key])) {
                    $found = false;
                    break;
                }
                $temp = $temp[$key];
            }

            if ($found) {
                $chatData = $temp;
                break;
            }
        }

        if ($chatData === null) {
            throw new LogicException(
                "Не удалось найти данные чата ('chat') в текущем событии.",
            );
        }

        return ChatDto::fromArray($chatData);
    }

    /**
     * Извлекает данные сообщения из любого подходящего поля в текущем событии.
     *
     * Этот метод универсален и ищет данные сообщения ('message') в таких
     * событиях, как message, callback_query, channel_post, my_chat_member и
     * других, корректно обрабатывая вложенные структуры.
     *
     * @return MessageDto Объект чата.
     * @throws LogicException Если данные чата не найдены в событии.
     *
     * @see https://zenithgram.github.io/classes/zenithMethods/get#getmessage
     */
    public function getMessage(): MessageDto
    {
        $update = $this->context->getUpdateData();

        $messageData = $update['message']
            ?? $update['callback_query']['message']
            ?? $update['edited_message']
            ?? $update['channel_post']
            ?? $update['edited_channel_post']
            ?? null;

        if ($messageData !== null) {
            return MessageDto::fromArray($messageData);
        }

        throw new LogicException(
            "Не удалось найти данные сообщения ('Message') в текущем событии.",
        );
    }

    private array $botButtons = [];

    /**
     * @internal
     */
    public function setBotButtons(array $buttons): void
    {
        $this->botButtons = $buttons;
    }

    /**
     * @internal
     */
    public function getBotButtons(): array
    {
        return $this->botButtons;
    }
}