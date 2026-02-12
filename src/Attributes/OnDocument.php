<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех документов.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnDocument
{
    public function __construct() {}
}