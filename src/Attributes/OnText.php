<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для точного совпадения текста.
 *
 * @param string|array $text Текст для совпадения.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnText
{
    public function __construct(
        public string|array $text
    ) {}
}