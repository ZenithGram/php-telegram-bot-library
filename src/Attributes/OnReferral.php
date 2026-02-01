<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для реферальной ссылки.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnReferral
{
    public function __construct() {}
}