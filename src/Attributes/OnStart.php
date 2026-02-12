<?php
declare(strict_types=1);

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