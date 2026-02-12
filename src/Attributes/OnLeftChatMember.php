<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут вышедшего участника чата.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnLeftChatMember
{
    public function __construct() {}
}