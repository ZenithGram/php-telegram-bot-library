<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Создает маршрут для всех текстовых сообщений (message fallback).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnMessage
{
    public function __construct() {}
}