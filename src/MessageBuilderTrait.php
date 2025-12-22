<?php

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Utils\LocalFile;

use function Amp\File\exists;

trait MessageBuilderTrait
{

    protected array $mediaQueue = [];
    protected bool $sendDice = false;
    protected bool $sendSticker = false;
    protected array $messageData
        = [
            'text'                => '',
            'reply_markup'        => '',
            'reply_markup_raw'    => [],
            'params'              => [],
            'parseMode'           => MessageParseMode::None,
            'reply_to_message_id' => '',
            'emoji'               => '',

        ];

    /**
     * Задает текст сообщения, которое будет отправлено в ответ
     *
     * @param string $text Текст сообщения
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/text
     */
    public function text(string $text = ''): self
    {
        $this->messageData['text'] = $text;

        return $this;
    }

    /**
     * Добавляет дополнительные параметры к сообщению.
     *
     * @param array $params Массив с дополнительными параметрами
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/params
     */
    public function params(array $params): self
    {
        $this->messageData['params'] = array_merge(
            $this->messageData['params'], $params,
        );

        return $this;
    }

    /**
     * Устанавливает режим парсинга сообщения
     *
     * @param MessageParseMode $parseMode
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/params
     */
    public function parseMode(MessageParseMode $parseMode): self
    {
        $this->messageData['parseMode'] = $parseMode;

        return $this;
    }

    /**
     * Добавляет "сущность" с форматированием к сообщению
     *
     * @param array $entities Массив с форматированием
     *
     * @return self
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/messageMethods/entity
     */
    public function entities(array $entities): self
    {
        $this->messageData['entities'] = json_encode(
            $entities, JSON_THROW_ON_ERROR,
        );

        return $this;
    }

    /**
     * Устанавливает режим ответа на сообщение.
     *
     * @param int|null $message_id ID сообщения для ответа. Если null, отвечает
     *                             на текущее сообщение из контекста.
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/reply
     */
    public function reply(int|null $message_id = null): self
    {
        if ($message_id === null) {
            $message_id = $this->context->getMessageId();
        }

        $this->messageData['reply_to_message_id'] = $message_id;

        return $this;
    }

    /* reply_markup */


    /**
     * Добавляет клавиатуру к сообщению
     *
     * @param array $buttons  Кнопки клавиатуры
     * @param bool  $one_time Показывать клавиатуру однократно?
     * @param bool  $resize   Растягивать клавиатуру?
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/kbd
     */
    public function kbd(array $buttons, bool $one_time = false,
        bool $resize = true,
    ): self {
        $reply_markup = [
            'keyboard'          => $buttons,
            'resize_keyboard'   => $resize,
            'one_time_keyboard' => $one_time,
        ];

        $this->messageData['reply_markup_raw'] = $reply_markup;

        return $this;
    }

    /**
     * Добавляет inline-клавиатуру к сообщению
     *
     * @param array $buttons Кнопки клавиатуры
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/actionMethods/inlineKbd
     */
    public function inlineKbd(array $buttons): self
    {
        $reply_markup = [
            'inline_keyboard' => $buttons,
        ];

        $this->messageData['reply_markup_raw'] = $reply_markup;

        return $this;
    }

    /**
     * Удаляет клавиатуру
     *
     * @return self
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/actionMethods/removeKbd
     */
    public function removeKbd(): self
    {
        $reply_markup = ['remove_keyboard' => true];

        $this->messageData['reply_markup'] = json_encode(
            $reply_markup, JSON_THROW_ON_ERROR,
        );

        return $this;
    }

    /**
     * Включает режим ForceReply
     *
     * @param string $placeholder По умолчанию - ''
     * @param bool   $selective   По умолчанию - false
     *
     * @return self
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/actionMethods/forceReply
     */
    public function forceReply(string $placeholder = '', bool $selective = false,
    ): self {
        $reply_markup = [
            'force_reply'             => true,
            'input_field_placeholder' => $placeholder,
            'selective'               => $selective,
        ];

        $this->messageData['reply_markup'] = json_encode(
            $reply_markup, JSON_THROW_ON_ERROR,
        );

        return $this;
    }

    /* Медиа */

    private function detectInput(string $input): string|LocalFile
    {
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return $input;
        }

        if (exists($input)) {
            return new LocalFile($input);
        }

        return $input;
    }

    private function addMedia(string $type, string|array $input): self
    {
        $inputs = is_array($input) ? $input : [$input];

        foreach ($inputs as $item) {
            $this->mediaQueue[] = [
                'type'    => $type,
                'payload' => $this->detectInput($item),
            ];
        }

        return $this;
    }

    /**
     * Добавляет изображение к сообщению
     *
     * @param string|array $img Ссылка или массив ссылок (FileID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/img
     */
    public function img(string|array $img): self
    {
        $this->addMedia('photo', $img);

        return $this;
    }

    /**
     * Отправляет анимированные эмодзи
     *
     * @param string $dice '🎲', '🎯', '🏀', '⚽', '🎳', '🎰'
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/dice
     */
    public function dice(string $dice): self
    {
        $this->sendDice = true;
        $this->messageData['emoji'] = $dice;

        return $this;
    }

    /**
     * Добавляет gif-файл к сообщению
     *
     * @param string|array $gif Ссылка или массив ссылок (ID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/gif
     */
    public function gif(string|array $gif): self
    {
        $this->addMedia('animation', $gif);

        return $this;
    }

    /**
     * Отправляет голосовое сообщение
     *
     * @param string $voice Ссылка (ID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/voice
     */
    public function voice(string $voice): self
    {
        $this->addMedia('voice', $voice);

        return $this;
    }

    /**
     * Добавляет аудио-файл к сообщению
     *
     * @param string|array $audio Ссылка или массив ссылок (ID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/audio
     */
    public function audio(string|array $audio): self
    {
        $this->addMedia('audio', $audio);

        return $this;
    }

    /**
     * Добавляет видео-файл к сообщению
     *
     * @param string|array $video Ссылка или массив ссылок (ID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/video
     */
    public function video(string|array $video): self
    {
        $this->addMedia('video', $video);

        return $this;
    }

    /**
     * Добавляет документ к сообщению
     *
     * @param string|array $document Ссылка или массив ссылок (ID)
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/doc
     */
    public function doc(string|array $document): self
    {
        $this->addMedia('document', $document);

        return $this;
    }

//    /**
//     * Добавляет превью к сообщению с помощью ссылки.
//     *
//     * @param string $url
//     *
//     * @return self
//     *
//     * @see https://zenithgram.github.io/classes/messageMethods/mediaPreview
//     */
//    public function mediaPreview(string $url): self
//    {
//        $this->media_preview_url = $url;
//
//        return $this;
//    }

    /**
     * Отправляет стикер
     *
     * @param string $sticker
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/messageMethods/sticker
     */
    public function sticker(string $sticker): self
    {
        $this->sendSticker = true;
        $this->messageData['sticker'] = $sticker;

        return $this;
    }

    public function getMessageData(): array
    {
        return $this->messageData;
    }

    public function setMessageData(array $messageData): self
    {
        $this->messageData = $messageData;

        return $this;
    }
}