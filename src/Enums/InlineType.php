<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

enum InlineType: string
{
    /** Обычный текст, статья или ссылка */
    case Article = 'article';

    /** Геолокация (координаты) */
    case Location = 'location';

    /** Анимация в формате MP4 (без звука) */
    case Mpeg4Gif = 'mpeg4_gif';

    /** Место (координаты + название и адрес) */
    case Venue = 'venue';

    /** Фотография */
    case Photo = 'photo';

    /** GIF-анимация */
    case Gif = 'gif';

    /** Видеофайл */
    case Video = 'video';

    /** Аудиофайл (музыка) */
    case Audio = 'audio';

    /** Голосовое сообщение */
    case Voice = 'voice';

    /** Документ или любой файл */
    case Document = 'document';
}