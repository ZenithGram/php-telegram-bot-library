<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

enum PaginationMode: int
{
    /**
     * Стандартные стрелки навигации "Предыдущая страница" и "Следующая
     * страница"
     */
    case ARROWS = 0;    // < >

    /**
     * Несколько номеров страниц на строке
     */
    case NUMBERS = 1;   // 1 2 3
}