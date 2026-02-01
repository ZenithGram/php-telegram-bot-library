<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех сообщений с фото.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnPhoto
{
    public function __construct() {}
}