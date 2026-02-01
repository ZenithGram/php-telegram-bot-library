<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для callback-запроса.
 *
 * @param string|array $data Данные из callback-кнопки.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnCallback
{
    public function __construct(
        public string|array $data
    ) {}
}