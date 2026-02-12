<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех сообщений с аудио.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnAudio
{
    public function __construct() {}
}