<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех видео-сообщений (кружочков).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnVideoNote
{
    public function __construct() {}
}