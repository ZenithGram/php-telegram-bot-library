<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Enums\ButtonStyle;

class Button
{
    private static function buildBase(string $text, string $styleValue,
        ?string $customEmojiId,
    ): array {
        $button = ['text' => $text];

        if ($styleValue !== '') {
            $button['style'] = $styleValue;
        }

        if ($customEmojiId !== null) {
            $button['icon_custom_emoji_id'] = $customEmojiId;
        }

        return $button;
    }

    /**
     * Inline кнопка с Callback Data
     *
     * @param string      $text          Текст кнопки
     * @param string      $data          CallbackData кнопки (1-64 байта)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#cb
     */
    public static function cb(string $text, string $data,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['callback_data' => $data],
        );
    }

    /**
     * Inline кнопка-ссылка
     *
     * @param string      $text          Текст кнопки
     * @param string      $url           HTTP или tg:// ссылка
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#url
     */
    public static function url(string $text, string $url,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['url' => $url],
        );
    }

    /**
     * Кнопка для WebApp (подходит для Inline и Reply)
     *
     * @param string      $text          Текст кнопки
     * @param string      $url           Ссылка на приложение (HTTPS)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#webApp
     */
    public static function webApp(string $text, string $url,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['web_app' => ['url' => $url]],
        );
    }

    /**
     * Inline кнопка авторизации (Telegram Login)
     *
     * @param string      $text          Текст кнопки
     * @param array       $loginUrl      Массив параметров LoginUrl (url,
     *                                   forward_text и т.д.)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#loginUrl
     */
    public static function loginUrl(string $text, array $loginUrl,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['login_url' => $loginUrl],
        );
    }

    /**
     * Inline кнопка переключения в инлайн-режим (в другом чате)
     *
     * @param string      $text          Текст кнопки
     * @param string      $query         Запрос, который будет вставлен (может
     *                                   быть пустым)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#switchInline
     */
    public static function switchInline(string $text, string $query = '',
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['switch_inline_query' => $query],
        );
    }

    /**
     * Inline кнопка переключения в инлайн-режим в текущем чате
     *
     * @param string      $text          Текст кнопки
     * @param string      $query         Запрос, который будет вставлен (может
     *                                   быть пустым)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#switchInlineCurrent
     */
    public static function switchInlineCurrent(string $text, string $query = '',
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['switch_inline_query_current_chat' => $query],
        );
    }

    /**
     * Inline кнопка переключения в инлайн-режим с выбором типа чата
     *
     * @param string      $text          Текст кнопки
     * @param array       $chosenChat    Параметры объекта
     *                                   SwitchInlineQueryChosenChat (allow_channel_chats, allow_user_chats и т.д.)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#switchInlineChosen
     */
    public static function switchInlineChosen(string $text, array $chosenChat,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['switch_inline_query_chosen_chat' => $chosenChat],
        );
    }

    /**
     * Inline кнопка копирования текста
     *
     * @param string      $text          Текст на кнопке
     * @param string      $textToCopy    Текст, который скопируется в буфер
     *                                   (1-256 символов)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#copyText
     */
    public static function copyText(string $text, string $textToCopy,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['copy_text' => ['text' => $textToCopy]],
        );
    }

//    /**
//     * Inline кнопка для запуска HTML5 игры
//     *
//     * @param string      $text          Текст кнопки
//     * @param ButtonStyle $style         Стиль кнопки
//     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
//     *
//     * @return array
//     * @see https://zenithgram.github.io/classes/button#callbackGame
//     */
//    public static function callbackGame(string $text,
//        ButtonStyle $style = ButtonStyle::None,
//        ?string $customEmojiId = null,
//    ): array {
//        return array_merge(
//            self::buildBase($text, $style->value, $customEmojiId),
//            ['callback_game' => new \stdClass()],
//        );
//    }

    /**
     * Inline кнопка оплаты (Invoice). Должна быть первой в первом ряду.
     *
     * @param string      $text          Текст кнопки
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#pay
     */
    public static function pay(string $text,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['pay' => true],
        );
    }

    /**
     * Текстовая кнопка (для Reply)
     *
     * @param string      $text          Текст кнопки
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#text
     */
    public static function text(string $text,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return self::buildBase($text, $style->value, $customEmojiId);
    }

    /**
     * Кнопка запроса контакта
     *
     * @param string      $text          Текст кнопки
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#contact
     */
    public static function contact(string $text,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_contact' => true],
        );
    }

    /**
     * Кнопка запроса геолокации
     *
     * @param string      $text          Текст кнопки
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#location
     */
    public static function location(string $text,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_location' => true],
        );
    }

    /**
     * Кнопка запроса пользователей (выбор юзеров/ботов)
     *
     * @param string      $text          Текст кнопки
     * @param int         $requestId     ID запроса (будет возвращен боту)
     * @param array       $options       Параметры объекта
     *                                   KeyboardButtonRequestUsers
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#requestUsers
     */
    public static function requestUsers(string $text, int $requestId,
        array $options = [],
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        $options['request_id'] = $requestId;

        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_users' => $options],
        );
    }

    /**
     * Кнопка запроса чата/канала
     *
     * @param string      $text          Текст кнопки
     * @param int         $requestId     ID запроса (будет возвращен боту)
     * @param bool        $chatIsChannel True для запроса канала, False для
     *                                   запроса группы
     * @param array       $options       Параметры объекта
     *                                   KeyboardButtonRequestChat
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#requestChat
     */
    public static function requestChat(string $text, int $requestId,
        bool $chatIsChannel, array $options = [],
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        $options['request_id'] = $requestId;
        $options['chat_is_channel'] = $chatIsChannel;

        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_chat' => $options],
        );
    }

    /**
     * Кнопка запроса создания управляемого бота
     *
     * @param string      $text          Текст кнопки
     * @param int         $requestId     ID запроса
     * @param array       $options       Параметры объекта
     *                                   KeyboardButtonRequestManagedBot
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#requestManagedBot
     */
    public static function requestManagedBot(string $text, int $requestId,
        array $options = [],
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        $options['request_id'] = $requestId;

        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_managed_bot' => $options],
        );
    }

    /**
     * Кнопка создания опроса (пользователь создает и отправляет боту)
     *
     * @param string      $text          Текст кнопки
     * @param string|null $type          Тип опроса ('quiz', 'regular' или null
     *                                   для любого)
     * @param ButtonStyle $style         Стиль кнопки
     * @param string|null $customEmojiId Пользовательский эмодзи перед кнопкой
     *
     * @return array
     * @see https://zenithgram.github.io/classes/button#poll
     */
    public static function poll(string $text, ?string $type = null,
        ButtonStyle $style = ButtonStyle::None,
        ?string $customEmojiId = null,
    ): array {
        $requestPoll = $type !== null ? ['type' => $type] : new \stdClass();

        return array_merge(
            self::buildBase($text, $style->value, $customEmojiId),
            ['request_poll' => $requestPoll],
        );
    }
}