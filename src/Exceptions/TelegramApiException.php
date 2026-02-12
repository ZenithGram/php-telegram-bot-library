<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Exceptions;

class TelegramApiException extends ZenithGramException
{
    private array $parameters;

    public function __construct(string $message, int $code = 0, array $parameters = [])
    {
        parent::__construct($message, $code);
        $this->parameters = $parameters;
    }

    /**
     * Возвращает параметры запроса, который вызвал ошибку (для отладки)
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}