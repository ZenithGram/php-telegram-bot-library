<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для команды бота (например, '/start').
 *
 * @param string|array $command Текст команды бота.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnBotCommand
{
    public function __construct(
        public string|array $command
    ) {}
}