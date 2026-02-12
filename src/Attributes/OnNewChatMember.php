<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для нового(ых) участника(ов) чата.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnNewChatMember
{
    public function __construct() {}
}