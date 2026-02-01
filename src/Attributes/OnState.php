<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для обработки конкретного состояния (шага диалога).
 *
 * @param string $name Название состояния (например, 'ask_age').
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnState
{
    public function __construct(
        public string $name
    ) {}
}