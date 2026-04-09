<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

/**
 * Перечисление стилей для кнопок (Inline и Reply).
 */
enum ButtonStyle: string
{
    /**
     * Красная кнопка (Danger).
     */
    case Danger = 'danger';

    /**
     * Зеленая кнопка (Success).
     */
    case Success = 'success';

    /**
     * Синяя кнопка (Primary).
     */
    case Primary = 'primary';

    /**
     * Стандартный стиль (определяется приложением Telegram).
     */
    case None = '';
}