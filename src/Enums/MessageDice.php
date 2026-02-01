<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

enum MessageDice: string
{
    /**
     * Кубик (Значения: 1-6)
     */
    case Dice = '🎲';

    /**
     * Дартс (Значения: 1-6)
     */
    case Darts = '🎯';

    /**
     * Баскетбол (Значения: 1-5)
     */
    case Basketball = '🏀';

    /**
     * Футбол (Значения: 1-5)
     */
    case Football = '⚽';

    /**
     * Боулинг (Значения: 1-6)
     */
    case Bowling = '🎳';

    /**
     * Казино (Значения: 1-64)
     */
    case Casino = '🎰';
}