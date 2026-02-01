<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

enum ChatAction: string
{
    /**
     * Пользователь увидит: "печатает..."
     */
    case Typing = 'typing';

    /**
     * Пользователь увидит: "отправляет фото..."
     */
    case UploadPhoto = 'upload_photo';

    /**
     * Пользователь увидит: "отправляет видео..."
     */
    case UploadVideo = 'upload_video';

    /**
     * Пользователь увидит: "записывает видео..."
     */
    case RecordVideo = 'record_video';

    /**
     * Пользователь увидит: "записывает голосовое..."
     */
    case RecordVoice = 'record_voice';

    /**
     * Пользователь увидит: "отправляет голосовое..."
     */
    case UploadVoice = 'upload_voice';

    /**
     * Пользователь увидит: "отправляет файл..."
     */
    case UploadDocument = 'upload_document';

    /**
     * Пользователь увидит: "выбирает стикер..."
     */
    case ChooseSticker = 'choose_sticker';

    /**
     * Пользователь увидит: "выбирает локацию..."
     */
    case FindLocation = 'find_location';

    /**
     * Пользователь увидит: "записывает видеосообщение..."
     */
    case RecordVideoNote = 'record_video_note';

    /**
     * Пользователь увидит: "отправляет видеосообщение..."
     */
    case UploadVideoNote = 'upload_video_note';
}