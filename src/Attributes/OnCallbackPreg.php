<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для callback-запроса по регулярному выражению.
 *
 * @param string|array $pattern Регулярное выражение для CallbackData.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnCallbackPreg
{
    public function __construct(
        public string|array $pattern
    ) {}
}