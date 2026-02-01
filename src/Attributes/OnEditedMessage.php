<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для редактирования сообщения.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnEditedMessage
{
    public function __construct() {}
}