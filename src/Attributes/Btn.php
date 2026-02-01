<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для кнопки.
 *
 * @param string $id Уникальный идентификатор кнопки.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Btn
{
    public function __construct(
        public string $id
    ) {}
}