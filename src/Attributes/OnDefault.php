<?php

namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

/**
 * Устанавливает обработчик по умолчанию (общий fallback).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnDefault
{
    public function __construct() {}
}