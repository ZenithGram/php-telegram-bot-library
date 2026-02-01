<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для команды /start.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnStart
{
    public function __construct() {}
}