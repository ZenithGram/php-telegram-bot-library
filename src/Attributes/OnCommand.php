<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для пользовательской команды (например, '!start').
 *
 * @param string|array $command Текст команды.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnCommand
{
    public function __construct(
        public string|array $command
    ) {}
}