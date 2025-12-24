<?php

namespace ZenithGram\ZenithGram\Enums;

enum PaginationLayout: int
{
    /**
     * Все 4 кнопки будут находиться на одной строке
     *
     * @see https://zenithgram.github.io/classes/paginationMethods/setNavigationLayout#возможные-значения-константы
     *
     */
    case ROW = 0;

    /**
     * Кнопки "Предыдущая страница" и "Следующая страница" будут находиться на
     * одной строке
     *
     * Кнопки "Первая страница" и "Последняя страница" будут находиться на
     * второй строке
     *
     * @see https://zenithgram.github.io/classes/paginationMethods/setNavigationLayout#возможные-значения-константы
     *
     */
    case SPLIT = 1;

    /**
     * Кнопки разных типов будут находиться на одной строке только при условии,
     * что их 2
     *
     * Иначе будут на разных
     *
     * @see https://zenithgram.github.io/classes/paginationMethods/setNavigationLayout#возможные-значения-константы
     *
     */
    case SMART = 2;
}