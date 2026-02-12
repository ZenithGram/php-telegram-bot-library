<?php
declare(strict_types=1);

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