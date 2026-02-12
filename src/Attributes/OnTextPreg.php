<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для текста по регулярному выражению.
 *
 * @param string|array $pattern Регулярное выражение.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnTextPreg
{
    public function __construct(
        public string|array $pattern
    ) {}
}