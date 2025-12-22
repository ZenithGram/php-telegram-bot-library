<?php

namespace ZenithGram\ZenithGram;

class Action
{
    use MessageBuilderTrait;

    private string $id;
    private mixed $condition;
    private $handler;
    public string $queryText = '';
    public string $redirect_to = '';
    public \Closure|null $middleware_handler = null;
    private array $access_ids = [];
    private array $no_access_ids = [];
    private \Closure|null $access_handler = null;
    private \Closure|null $no_access_handler = null;
    private int $messageDataAction = 0; // 0 - send, 1 - editText, 2 - editCaption, 3 - editMedia

    public function __construct(string $id, mixed $condition)
    {
        $this->id = $id;
        $this->condition = $condition;
    }

    /**
     * Устанавливает middleware для маршрута.
     *
     * @param callable $handler Обработчик
     *
     * @return Bot
     *
     * @see https://zenithgram.github.io/classes/actionMethods/middleware
     */
    public function middleware(callable $handler): self
    {
        $this->middleware_handler = $handler(...);

        return $this;
    }

    /**
     * Устанавливает обработчик для маршрута.
     *
     * @param callable $handler Обработчик
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/func
     */
    public function func(callable $handler): self
    {
        $this->handler = $handler(...);

        return $this;
    }

    /**
     * Перенаправляет один маршрут на другой.
     * Копирует обработчик и данные ответа из маршрута $id в текущий маршрут.
     *
     * @param string $id ID маршрута, куда перенаправлять
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/redirect
     */
    public function redirect(string $id): self
    {
        $this->redirect_to = $id;

        return $this;
    }

    /**
     * Задает всплывающий текст при нажатии на кнопку
     *
     * @param string $query Всплывающий текст
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/query
     */
    public function query(string $query): self
    {
        return $this->setQueryText($query);
    }

    /**
     * Устанавливает список ID пользователей, которым доступен маршрут
     *
     * @param int|array     $ids     Идентификаторы пользователей
     * @param callable|null $handler Обработчик, если доступ к маршруту запрещен
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/access
     */
    public function access(int|array $ids, ?callable $handler = null): self
    {
        $this->access_ids = is_numeric($ids) ? [$ids] : $ids;
        $this->access_handler = ($handler !== null) ? $handler(...) : null;

        return $this;
    }

    /**
     * Устанавливает список ID пользователей, которым не доступен маршрут
     *
     * @param int|array     $ids     Идентификаторы пользователей
     * @param callable|null $handler Обработчик, если доступ к маршруту запрещен
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/noAccess
     */
    public function noAccess(int|array $ids, ?callable $handler = null): self
    {
        $this->no_access_ids = is_numeric($ids) ? [$ids] : $ids;

        $this->no_access_handler = ($handler !== null) ? $handler(...) : null;

        return $this;
    }

    public function getQueryText(): string
    {
        return $this->queryText;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCondition(): mixed
    {
        return $this->condition;
    }

    public function getHandler(): ?callable
    {
        return $this->handler;
    }

    public function getAccessIds(): array
    {
        return $this->access_ids;
    }

    public function getNoAccessIds(): array
    {
        return $this->no_access_ids;
    }

    public function getAccessHandler(): ?callable
    {
        return $this->access_handler;
    }

    public function getNoAccessHandler(): ?callable
    {
        return $this->no_access_handler;
    }

    public function setHandler(?callable $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function setQueryText(?string $queryText): self
    {
        $this->queryText = $queryText;

        return $this;
    }

    /**
     * Изменяет текст сообщения
     *
     * @param string $text Новый текст
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/editText
     */
    public function editText(string $text = ''): self
    {
        $this->messageData['text'] = $text;
        $this->messageDataAction = 1;

        return $this;
    }

    /**
     * Изменяет текст описания
     *
     * @param string $text Новый текст
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/editCaption
     */
    public function editCaption(string $text = ''): self
    {
        $this->messageData['text'] = $text;
        $this->messageDataAction = 2;

        return $this;
    }

    /**
     * Изменяет медиа в сообщении
     *
     * @return Action
     *
     * @see https://zenithgram.github.io/classes/actionMethods/editMedia
     */
    public function editMedia(): self
    {
        $this->messageDataAction = 3;

        return $this;
    }

    public function getMessageDataAction(): int
    {
        return $this->messageDataAction;
    }
}