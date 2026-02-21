<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для кнопки.
 *
 * @param string $id Уникальный идентификатор кнопки.
 * @param string $text Текст кнопки.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Btn
{
    public function __construct(
        public string $id, public string $text
    ) {}
}