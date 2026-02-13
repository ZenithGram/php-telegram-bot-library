<?php

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Enums\MessageParseMode;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;
use ZenithGram\ZenithGram\Exceptions\TelegramApiException;

final class MessageDraft
{
    private UpdateContext $context;
    private ZG $ZG;
    private string $currentText;
    private string $parse_mode = '';
    private array $chatInfo = [];
    private string $entities = '';

    public function __construct(?string $text, ZG $ZG)
    {
        $this->currentText = $text;
        $this->context = $ZG->context;
        $this->ZG = $ZG;
    }

    /**
     * Устанавливает новый актуальный текст сообщения
     *
     * @param string $text
     *
     * @return self
     *
     * @see https://zenithgram.github.io/classes/draftMethods/setText
     */
    public function setText(string $text): self
    {
        $this->currentText = $text;

        return $this;
    }

    /**
     * Задает режим парсинга
     *
     * @param MessageParseMode $mode
     *
     * @return MessageDraft
     *
     * @see https://zenithgram.github.io/classes/draftMethods/parseMode
     */
    public function parseMode(MessageParseMode $mode): self
    {
        $this->parse_mode = $mode->value;

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
     * @see https://zenithgram.github.io/classes/draftMethods/entities
     */
    public function entities(array $entities): self
    {
        $this->entities = json_encode(
            $entities, JSON_THROW_ON_ERROR,
        );

        return $this;
    }

    /**
     * Отправляет запрос в телеграм для изменения/отправки текста
     *
     * @param int|string|null $chat_id
     * @param int|null        $message_thread_id
     * @param int|null        $draft_id
     *
     * @return self
     * @throws ZenithGramException
     *
     * @see https://zenithgram.github.io/classes/draftMethods/send
     */
    public function send(int|string|null $chat_id = null,
        int|null $message_thread_id = null,
        int|null $draft_id = null,
    ): self {
        if ($this->chatInfo === []) {
            $chat_info = ['chat_id'  => $chat_id ?: $this->context->getChatId(),
                          'draft_id' => $draft_id ?: time()];

            $thread_id = $message_thread_id ?? $this->ZG->getMsgThreadId();
            if ($thread_id !== null) {
                $chat_info['message_thread_id'] = $thread_id;
            }

            $this->chatInfo = $chat_info;
        }

        try {
            $this->ZG->callAPI(
                'sendMessageDraft',
                array_merge($this->chatInfo, ['text'       => $this->currentText,
                                              'parse_mode' => $this->parse_mode,
                                              'entities'   => $this->entities]),
            );
        } catch (\Throwable $e) {
            $this->ZG->reportException($e);
        }

        return $this;
    }
}