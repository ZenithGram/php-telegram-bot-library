<?php

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Dto\UserDto;
use ZenithGram\ZenithGram\Storage\StorageInterface;
use ZenithGram\ZenithGram\Utils\DependencyResolver;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class Bot
{

    private ZG|null $tg;
    private UpdateContext|null $context;
    private array $ctx = [];
    private ?StorageInterface $storage = null;
    private DependencyResolver $resolver;

    private array $routes
        = [
            'bot_command'         => [],
            'command'             => [],
            'text_exact'          => [],
            'text_preg'           => [],
            'callback_query'      => [],
            'callback_query_preg' => [],
            'state'               => [],
            'inline_fallback'     => null,
            'start_command'       => null,
            'referral_command'    => null,
            'edit_message'        => null,
            'sticker_fallback'    => null,
            'message_fallback'    => null,
            'photo_fallback'      => null,
            'video_fallback'      => null,
            'audio_fallback'      => null,
            'voice_fallback'      => null,
            'document_fallback'   => null,
            'video_note_fallback' => null,
            'new_chat_members'    => null,
            'left_chat_member'    => null,
            'fallback'            => null,
        ];

    public array $buttons
        = [
            'btn'    => [],
            'action' => [],
        ];

    private array $pendingRedirects = [];

    private \Closure|null $middleware_handler = null;

    public function __construct(ZG|null $tg = null)
    {
        $this->tg = $tg;
        $this->context = $tg?->context;
        $this->resolver = new DependencyResolver();
    }

    /**
     * Устанавливает PSR-11 Контейнер для внедрения зависимостей
     *
     * @param ContainerInterface $container Объект реализации кеша
     *
     * @return Bot
     *
     * @see https://zenithgram.github.io/classes/botMethods/setContainer
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->resolver->setContainer($container);

        return $this;
    }

    /**
     * Устанавливает PSR-16 кеш для рефлексии
     *
     * @param CacheInterface $cache Объект реализации кеша
     *
     * @return Bot
     *
     * @see https://zenithgram.github.io/classes/botMethods/setCache
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->resolver->setCache($cache);

        return $this;
    }

    /**
     * Подключает хранилище состояний к боту (файлы, БД, Redis)
     *
     * @param StorageInterface $storage Объект реализации хранилища
     *
     * @return Bot
     *
     * @see https://zenithgram.github.io/classes/botMethods/setStorage
     */
    public function setStorage(StorageInterface $storage): self
    {
        $this->storage = $storage;
        // Прокидываем хранилище в основной класс ZG, чтобы методы step() работали
        $this->tg?->setStorage($storage);

        return $this;
    }

    /**
     * Создает маршрут для обработки конкретного состояния (шага диалога)
     *
     * @param string $stateName Название состояния (например, 'ask_age')
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onState
     */
    public function onState(string $stateName): Action
    {
        $route = new Action("state_{$stateName}", $stateName);
        $this->routes['state'][$stateName] = $route;

        return $route;
    }

    /**
     * Устанавливает middleware
     *
     * @param callable $handler Обработчик
     *
     * @return void
     *
     * @see https://zenithgram.github.io/classes/botMethods/middleware
     */
    public function middleware(callable $handler): void
    {
        $this->middleware_handler = \Closure::fromCallable($handler);
    }

    /**
     * Создает маршрут для кнопки.
     *
     * @param string      $id   Уникальный идентификатор кнопки.
     * @param string|null $text Текст кнопки.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/btn
     */
    public function btn(string $id, string|null $text = null): Action
    {
        $this->buttons['btn'][$id] = $text ?? $id;

        $route = new Action($id, $text ?? $id);
        $this->buttons['action'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для команды.
     *
     * @param string            $id      Уникальный идентификатор маршрута.
     * @param array|string|null $command Текст команды бота, например '/start'.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onBotCommand
     */
    public function onBotCommand(string $id, array|string|null $command = null,
    ): Action {
        $route = new Action($id, $command ?? $id);
        $this->routes['bot_command'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для команды /start.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onStart
     */
    public function onStart(): Action
    {
        $route = new Action('start_command', null);
        $this->routes['start_command'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для реферальной ссылки.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onReferral
     */
    public function onReferral(): Action
    {
        $route = new Action('referral_command', null);
        $this->routes['referral_command'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для редактирования сообщения.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onEditedMessage
     */
    public function onEditedMessage(): Action
    {
        $route = new Action('edit_message', null);
        $this->routes['edit_message'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для команды.
     *
     * @param string            $id      Уникальный идентификатор маршрута.
     * @param array|string|null $command Текст команды, например '!start'.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onCommand
     */
    public function onCommand(string $id, array|string|null $command = null,
    ): Action {
        $route = new Action($id, $command ?? $id);
        $this->routes['command'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для точного совпадения текста.
     *
     * @param string            $id   Уникальный идентификатор.
     * @param array|string|null $text Текст для совпадения.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onText
     */
    public function onText(string $id, array|string|null $text = null): Action
    {
        $route = new Action($id, $text ?? $id);
        $this->routes['text_exact'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для текста по регулярному выражению.
     *
     * @param string            $id      Уникальный идентификатор.
     * @param array|string|null $pattern Регулярное выражение.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onTextPreg
     */
    public function onTextPreg(string $id, array|string|null $pattern = null,
    ): Action {
        $route = new Action($id, $pattern ?? $id);
        $this->routes['text_preg'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для callback-запроса.
     *
     * @param string            $id   Уникальный идентификатор.
     * @param array|string|null $data Данные из callback-кнопки.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onCallback
     */
    public function onCallback(string $id, array|string|null $data = null,
    ): Action {
        $route = new Action($id, $data ?? $id);
        $this->routes['callback_query'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для callback-запроса по регулярному выражению.
     *
     * @param string            $id      Уникальный идентификатор.
     * @param array|string|null $pattern Регулярное выражение для CallbackData.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onCallbackPreg
     */
    public function onCallbackPreg(string $id,
        array|string|null $pattern = null,
    ): Action {
        $route = new Action($id, $pattern ?? $id);
        $this->routes['callback_query_preg'][$id] = $route;

        return $route;
    }

    /**
     * Создает маршрут для inline-запроса.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onInline
     */
    public function onInline(): Action
    {
        $route = new Action('inline_fallback', null);
        $this->routes['inline_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех стикеров.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onSticker
     */
    public function onSticker(): Action
    {
        $route = new Action('sticker_fallback', null);
        $this->routes['sticker_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех текстовых сообщений.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onMessage
     */
    public function onMessage(): Action
    {
        $route = new Action('message_fallback', null);
        $this->routes['message_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех сообщений с фото.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onPhoto
     */
    public function onPhoto(): Action
    {
        $route = new Action('photo_fallback', null);
        $this->routes['photo_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех сообщений с видео.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onVideo
     */
    public function onVideo(): Action
    {
        $route = new Action('video_fallback', null);
        $this->routes['video_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех сообщений с аудио.
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onAudio
     */
    public function onAudio(): Action
    {
        $route = new Action('audio_fallback', null);
        $this->routes['audio_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех голосовых сообщений
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onVoice
     */
    public function onVoice(): Action
    {
        $route = new Action('voice_fallback', null);
        $this->routes['voice_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех документов
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onDocument
     */
    public function onDocument(): Action
    {
        $route = new Action('document_fallback', null);
        $this->routes['document_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для всех видео-сообщений
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onVideoNote
     */
    public function onVideoNote(): Action
    {
        $route = new Action('video_note_fallback', null);
        $this->routes['video_note_fallback'] = $route;

        return $route;
    }

    /**
     * Создает маршрут для нового(ых) участника(ов) чата
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onNewChatMember
     */
    public function onNewChatMember(): Action
    {
        $route = new Action('new_chat_members', null);
        $this->routes['new_chat_members'] = $route;

        return $route;
    }


    /**
     * Создает маршрут вышедшего участника чата
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onLeftChatMember
     */
    public function onLeftChatMember(): Action
    {
        $route = new Action('left_chat_member', null);
        $this->routes['left_chat_member'] = $route;

        return $route;
    }

    /**
     * Устанавливает обработчик по умолчанию (fallback).
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/botMethods/onDefault
     */
    public function onDefault(): Action
    {
        $route = new Action('fallback', null);
        $this->routes['fallback'] = $route;

        return $route;
    }

    private function dispatch(): void
    {
        $next = function(): void {
            $this->processRoutes();
        };

        if (is_callable($this->middleware_handler)) {
            $args = ['next' => $next];

            $dependencies = $this->resolver->resolve($this->middleware_handler, $this->tg, $args);

            // Вызываем middleware
            ($this->middleware_handler)(...$dependencies);
        } else {
            $next();
        }
    }

    private function processRoutes()
    {
        $type = $this->context->getType();
        $text = $this->context->getText();
        $callback_data = $this->context->getCallbackData();
        $userId = $this->context->getUserId();

        if ($type === 'bot_command'
            || ($type === "text"
                && str_starts_with($text, '/')
            )
        ) {
            $text = strtolower(mb_convert_encoding($text, 'UTF-8'));

            if (str_starts_with($text, '/start ')
                && $this->routes['referral_command'] !== null
            ) {
                $route = $this->routes['referral_command'];
                $this->dispatchAnswer(
                    $route, $type, [trim(mb_substr($text, 6))],
                );
                return;
            }

            if ($text === '/start'
                && $this->routes['start_command'] !== null
            ) {
                $route = $this->routes['start_command'];
                $this->dispatchAnswer($route, $type);
                return;
            }

            $words = explode(' ', $text);
            $command = $words[0];
            unset($words[0]);
            $final_text = implode(' ', $words);

            foreach ($this->routes['bot_command'] as $route) {
                $conditions = (array)$route->getCondition();
                foreach ($conditions as $condition) {
                    if ($condition === $command) {
                        $this->dispatchAnswer(
                            $route, $type, [$final_text],
                        );
                        return;
                    }
                }
            }
        }

        if ($this->storage && $userId) {
            $currentState = $this->storage->getState($userId);

            if ($currentState && isset($this->routes['state'][$currentState])) {
                $route = $this->routes['state'][$currentState];
                $this->dispatchAnswer(
                    $route, 'state', [$text ?? $callback_data],
                );
                return;
            }
        }

        if (($type === 'text' || $type === 'bot_command')) {
            if (!empty($text)) {
                foreach ($this->routes['command'] as $route) {
                    $conditions = (array)$route->getCondition();
                    foreach ($conditions as $commandPattern) {
                        if (str_contains($commandPattern, '%') || str_contains($commandPattern, '{')) {
                            $regex = $this->convertCommandPatternToRegex(
                                $commandPattern,
                            );
                            if (preg_match($regex, $text, $matches)) {
                                $args = $this->cleanMatches($matches);
                                $this->dispatchAnswer($route, $type, $args);
                                return;
                            }
                        } else {
                            $commandFromRoute = mb_convert_encoding(
                                $commandPattern, 'UTF-8',
                            );
                            if (str_starts_with($text, $commandFromRoute)) {
                                $commandLength = strlen($commandFromRoute);
                                if (!isset($text[$commandLength])
                                    || $text[$commandLength] === ' '
                                    || $text[$commandLength] === "\n"
                                ) {
                                    $argsString = trim(
                                        substr($text, $commandLength),
                                    );
                                    $args = ($argsString === '')
                                        ? []
                                        : preg_split(
                                            '/\s+/', $argsString, -1,
                                            PREG_SPLIT_NO_EMPTY,
                                        );
                                    $this->dispatchAnswer($route, $type, $args);
                                    return;
                                }
                            }
                        }
                    }
                }

                foreach ($this->routes['text_exact'] as $route) {
                    $conditions = (array)$route->getCondition();
                    foreach ($conditions as $condition) {
                        if ($condition === $text) {
                            $this->dispatchAnswer($route, $type);
                            return;
                        }
                    }
                }

                foreach ($this->buttons['action'] as $route) {
                    $conditions = (array)$route->getCondition();
                    foreach ($conditions as $condition) {
                        if ($condition === $text) {
                            $this->dispatchAnswer($route, 'text_button');
                            return;
                        }
                    }
                }

                foreach ($this->routes['text_preg'] as $route) {
                    $patterns = (array)$route->getCondition();
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $text, $matches)) {
                            // UPD: Используем cleanMatches
                            $args = $this->cleanMatches($matches);
                            $this->dispatchAnswer($route, $type, $args);
                            return;
                        }
                    }
                }

                if ($this->routes['message_fallback'] !== null) {
                    $this->dispatchAnswer(
                        $this->routes['message_fallback'], 'text',
                    );
                    return;
                }
            }

            if ($this->tryProcessFallbackMedia('photo')) return;
            if ($this->tryProcessFallbackMedia('audio')) return;
            if ($this->tryProcessFallbackMedia('video')) return;
            if ($this->tryProcessFallbackMedia('sticker')) return;
            if ($this->tryProcessFallbackMedia('voice')) return;
            if ($this->tryProcessFallbackMedia('document')) return;
            if ($this->tryProcessFallbackMedia('video_note')) return;

            if (!empty($this->context->getUpdateData()['message']['new_chat_members'])
                && $this->routes['new_chat_members'] !== null
            ) {
                $newMembersData = $this->context->getUpdateData()['message']['new_chat_members'];
                $newMembersDtos = array_map(
                    fn(array $memberData) => UserDto::fromArray($memberData),
                    $newMembersData,
                );
                $this->dispatchAnswer(
                    $this->routes['new_chat_members'],
                    'text',
                    $newMembersDtos,
                );
            }

            if (!empty($this->context->getUpdateData()['message']['left_chat_member'])
                && $this->routes['left_chat_member'] !== null
            ) {
                $leftMember = UserDto::fromArray(
                    $this->context->getUpdateData()['message']['left_chat_member'],
                );
                $this->dispatchAnswer(
                    $this->routes['left_chat_member'],
                    'text',
                    [$leftMember],
                );
            }
        }

        if ($type === 'callback_query') {
            foreach ($this->buttons['action'] as $route) {
                if ($route->getId() === $callback_data) {
                    $this->dispatchAnswer($route, 'button_'.$type);
                    return;
                }
            }

            foreach ($this->routes['callback_query'] as $route) {
                $conditions = (array)$route->getCondition();
                foreach ($conditions as $condition) {
                    if (str_contains($condition, '%') || str_contains($condition, '{')) {
                        $regex = $this->convertPatternToRegex($condition);
                        if (preg_match($regex, $callback_data, $matches)) {
                            $args = $this->cleanMatches($matches);
                            $this->dispatchAnswer($route, $type, $args);
                            return;
                        }
                    } else {
                        if ($condition === $callback_data) {
                            $this->dispatchAnswer($route, $type);
                            return;
                        }
                    }
                }
            }

            if (!empty($this->routes['callback_query_preg'])) {
                foreach ($this->routes['callback_query_preg'] as $route) {
                    $patterns = (array)$route->getCondition();
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $callback_data, $matches)) {
                            $args = $this->cleanMatches($matches);
                            $this->dispatchAnswer($route, $type, $args);
                            return;
                        }
                    }
                }
            }
        }

        if ($type === 'edited_message') {
            if ($this->routes['edit_message'] !== null) {
                $this->dispatchAnswer($this->routes['edit_message'], 'text');
                return;
            }
        }

        if ($type === 'inline_query') {
            if ($this->routes['inline_fallback'] !== null) {
                $this->dispatchAnswer($this->routes['inline_fallback'], $type);
                return;
            }
        }

        if ($this->routes['fallback'] !== null) {
            $this->dispatchAnswer($this->routes['fallback'], 'text');
            return;
        }
    }

    private function cleanMatches(array $matches): array
    {
        $args = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $args[$key] = $value;
            } elseif ($key > 0) {
                $args[] = $value;
            }
        }
        return $args;
    }

    private function tryProcessFallbackMedia(string $route_type): bool
    {
        $fallback_key = $route_type.'_fallback';

        // Проверяем, есть ли в сообщении медиа этого типа И существует ли для него fallback
        if (!empty($this->context->getUpdateData()['message'][$route_type])
            && $this->routes[$fallback_key] !== null
        ) {
            $file_id = File::getFileId(
                $this->context->getUpdateData(), $route_type,
            );
            $file = new File($file_id, $this->tg->api);

            $this->dispatchAnswer(
                $this->routes[$fallback_key],
                'text',
                [$file],
            );

            return true;
        }

        return false;
    }

    private function convertCommandPatternToRegex(string $pattern): string
    {
        $pattern = preg_replace(
            '/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>\S+)', $pattern,
        );

        preg_match_all(
            '/(?P<name>\(\?P<[^>]+>[^\)]+\))|%[swn]|\S+/u', $pattern, $matches,
        );
        $tokens = $matches[0];

        $regexParts = [];
        foreach ($tokens as $token) {
            if (str_starts_with($token, '(?P<')) {
                $regexParts[] = $token;
                continue;
            }

            switch ($token) {
                case '%s':
                    $regexParts[] = '(.+)';
                    break;
                case '%w':
                    $regexParts[] = '(\S+)';
                    break;
                case '%n':
                    $regexParts[] = '(\d+)';
                    break;
                default: // статическая часть
                    $regexParts[] = preg_quote($token, '/');
                    break;
            }
        }

        $regex = '^'.implode('\s+', $regexParts).'$';
        return '/'.$regex.'/u';
    }

    private function convertPatternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '/');
        $regex = preg_replace(
            '/\\\{([a-zA-Z0-9_]+)\\\}/', '(?P<$1>[a-zA-Z0-9_-]+)', $regex,
        );

        $replacements = [
            '%n' => '(\d+)',
            '%w' => '([a-zA-Z0-9_]+)',
            '%s' => '(.+)',
        ];

        $regex = str_replace(
            array_keys($replacements), array_values($replacements), $regex,
        );

        return '/^'.$regex.'$/u';
    }

    private function dispatchAnswer($route, $type, array $other_data = [])
    {
        $this->ctx = [
            $route,
            $type,
            $other_data,
        ];

        $this->tg->setBotButtons($this->buttons['btn']);

        $next = function(): void {
            $this->processAnswer();
        };

        if (is_callable($route->middleware_handler)) {
            $resolveArgs = array_merge($other_data, ['next' => $next]);

            $dependencies = $this->resolver->resolve($this->middleware_handler, $this->tg, $resolveArgs);
            ($this->middleware_handler)(...$dependencies);
        } else {
            $next();
        }
    }

    private function processAnswer()
    {
        [$route, $type, $other_data] = $this->ctx;

        if (!empty($route->redirect_to)) {
            $targetAction = $this->findActionById($route->redirect_to);
            if ($targetAction === null) {
                throw new \LogicException(
                    "Redirect target with ID '{$route->redirect_to}' not found.",
                );
            }
            $query_id = $this->context->getQueryId();
            if ($query_id && !empty($route->getQueryText())) {
                $this->tg->answerCallbackQuery(
                    $query_id, ['text' => $route->getQueryText()],
                );
            }
            return $this->executeAction($targetAction, $other_data);
        }

        $user_id = $this->context->getUserId();
        if ($user_id) {
            $accessIds = $route->getAccessIds();
            if (!empty($accessIds) && !in_array($user_id, $accessIds)) {
                $accessHandler = $route->getAccessHandler();
                if (is_callable($accessHandler)) {
                    $dependencies = $this->resolver->resolve($accessHandler, $this->tg, $other_data);
                    $accessHandler($dependencies);

                }
                return null;
            }
            $noAccessIds = $route->getNoAccessIds();
            if (!empty($noAccessIds) && in_array($user_id, $noAccessIds)) {
                $noAccessHandler = $route->getNoAccessHandler();
                if (is_callable($noAccessHandler)) {
                    $dependencies = $this->resolver->resolve($noAccessHandler, $this->tg, $other_data);
                    $noAccessHandler($dependencies);
                }
                return null;
            }
        }

        $handler = $route->getHandler();
        if (!empty($handler)) {
            $dependencies = $this->resolver->resolve($handler, $this->tg, $other_data);
            $handler(...$dependencies);

            return null;
        }

        if ($type === 'bot_command' || $type === 'text'
            || $type === 'text_button'
        ) {
            if (empty($route->getMessageData())) {
                return null;
            }
            $this->constructMessage($route);
            return null;
        }

        if ($type === 'state') {
            $type = $this->context->getType();
            if ($type === 'callback_query') {
                $this->tg->answerCallbackQuery(
                    $this->context->getQueryId(),
                    ['text' => $route->getQueryText()],
                );
            }
            $this->constructMessage($route);
            return null;
        }

        if ($type === 'button_callback_query') {
            $query_id = $this->context->getQueryId();
            $this->tg->answerCallbackQuery(
                $query_id, ['text' => $route->getQueryText()],
            );
            if (empty($route->getMessageData())) {
                $callback_data = $this->context->getCallbackData();
                foreach ($this->routes['callback_query'] as $route2) {
                    if ($route2->getCondition() === $callback_data) {
                        $this->dispatchAnswer($route2, 'callback_query');
                        return null;
                    }
                }
                return null;
            }
            $this->constructMessage($route);
            return null;
        }

        if ($type === 'callback_query') {
            $query_id = $this->context->getQueryId();
            $this->tg->answerCallbackQuery(
                $query_id, ['text' => $route->getQueryText()],
            );
            if (empty($route->getMessageData())) {
                return null;
            }
            $this->constructMessage($route);
            return null;
        }

        return null;
    }

    private function constructMessage(Action $action): array
    {
        $msg = new Message('', $this->tg);

        $msg->setAdditionallyParams($action->getAdditionallyParams());
        $msg->setMediaPreviewUrl($action->getMediaPreviewUrl());
        $msg->setMediaQueue($action->getMediaQueue());
        $msg->setMessageData($action->getMessageData());
        $msg->setReplyMarkupRaw($action->getReplyMarkupRaw());
        $msg->setSendType(...$action->getSendType());

        // 0 - send, 1 - editText, 2 - editCaption, 3 - editMedia
        $messageAction = $action->getMessageDataAction();
        if ($messageAction === 0) {
            return $msg->send();
        }
        if ($messageAction === 1) {
            return $msg->editText();
        }
        if ($messageAction === 2) {
            return $msg->editCaption();
        }

        return $msg->editMedia();
    }

    /**
     * Метод прокидывает основной класс ZG в класс Bot.
     *
     * Создан для удобного использования с методом получения обновления
     * LongPoll.
     *
     * @param ZG $ZG Объект класса ZG
     *
     * @return Bot
     *
     * @throws \InvalidArgumentException Если один из маршрутов не найден.
     *
     * @see https://zenithgram.github.io/classes/botMethods/zg
     */
    public function zg(ZG $ZG): self
    {
        $this->tg = $ZG;
        $this->context = $ZG->context;

        if ($this->storage) {
            $this->tg->setStorage($this->storage);
        }

        return $this;
    }

    /**
     * Метод является "сердцем" роутера. Он запускает процесс сопоставления
     * (диспетчеризации) входящего обновления с определенными вами маршрутами.
     *
     * Обычно run вызывается один раз в конце вашего скрипта без параметров.
     *
     * @param null|string $id =    Объект класса ZG
     *
     * @return void
     *
     * @throws \InvalidArgumentException Если один из маршрутов не найден.
     *
     * @see https://zenithgram.github.io/classes/botMethods/run
     */
    public function run(null|string $id = null): void
    {
        if ($id === null) {
            $this->processRedirects();

            $this->dispatch();
        } else {
            $actionToRun = $this->findActionById($id);

            if ($actionToRun === null) {
                throw new \InvalidArgumentException(
                    "Cannot run handler: Action with ID '$id' not found.",
                );
            }

            $this->executeAction($actionToRun);
        }
    }

    private function executeAction(Action $action, array $other_data = [])
    {
        $handler = $action->getHandler();
        if ($handler !== null) {
            // ИСПОЛЬЗУЕМ РЕЗОЛВЕР
            // $other_data содержит аргументы из regex (именованные и нет)
            $dependencies = $this->resolver->resolve($handler, $this->tg, $other_data);

            return $handler(...$dependencies);
        }

        if (!empty($action->getMessageData())) {
            return $this->constructMessage($action);
        }
        return null;
    }

    /**
     * Перенаправляет один маршрут на другой.
     * Копирует обработчик и данные ответа из маршрута $to_id в маршрут $id.
     *
     * @param string $id    ID исходного маршрута (откуда редирект).
     * @param string $to_id ID целевого маршрута (куда редирект).
     *
     * @return Bot
     *
     * @throws \InvalidArgumentException Если один из маршрутов не найден.
     *
     * @see https://zenithgram.github.io/classes/botMethods/redirect
     */
    public function redirect(string $id, string $to_id): Bot
    {
        $this->pendingRedirects[] = ['from' => $id, 'to' => $to_id];

        return $this;
    }

    private function processRedirects(): void
    {
        foreach ($this->pendingRedirects as $redirect) {
            $sourceAction = $this->findActionById($redirect['from']);
            if ($sourceAction === null) {
                throw new \LogicException(
                    "Redirect source route with ID '{$redirect['from']}' not found.",
                );
            }

            $targetAction = $this->findActionById($redirect['to']);
            if ($targetAction === null) {
                throw new \LogicException(
                    "Redirect target route with ID '{$redirect['to']}' not found.",
                );
            }

            $sourceAction
                ->setHandler($targetAction->getHandler())
                ->setMessageData($targetAction->getMessageData())
                ->setQueryText($targetAction->getQueryText());
        }

        $this->pendingRedirects = [];
    }

    private function findActionById(string $id): ?Action
    {
        foreach ($this->routes as $type => $actions) {
            if (is_array($actions) && isset($actions[$id])) {
                return $actions[$id];
            }
        }

        $singleActionRoutes = [
            'inline_fallback', 'start_command', 'referral_command',
            'edit_message', 'sticker_fallback', 'message_fallback',
            'photo_fallback', 'video_fallback', 'audio_fallback',
            'voice_fallback', 'document_fallback', 'video_note_fallback',
            'new_chat_members', 'left_chat_member', 'fallback',
        ];

        foreach ($singleActionRoutes as $routeName) {
            $action = $this->routes[$routeName];
            if ($action instanceof Action && $action->getId() === $id) {
                return $action;
            }
        }

        if (isset($this->buttons['action'][$id])) {
            return $this->buttons['action'][$id];
        }

        return null;
    }


}