<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех стикеров.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnSticker
{
    public function __construct() {}
}