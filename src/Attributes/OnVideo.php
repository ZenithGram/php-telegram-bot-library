<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех сообщений с видео.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnVideo
{
    public function __construct() {}
}