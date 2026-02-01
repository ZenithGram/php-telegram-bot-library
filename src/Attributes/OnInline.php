<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для inline-запроса.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnInline
{
    public function __construct() {}
}