<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех голосовых сообщений.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnVoice
{
    public function __construct() {}
}