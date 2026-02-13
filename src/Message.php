<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Enums\MessageParseMode;
use ZenithGram\ZenithGram\Utils\LocalFile;
use ZenithGram\ZenithGram\Exceptions\MessageBuilderException;

final class Message
{
    use MessageBuilderTrait;

    private ApiClient $api;
    private UpdateContext $context;
    private ZG $ZG;

    public function __construct(?string $text, ZG $ZG)
    {
        $this->messageData['text'] = $text;
        $this->api = $ZG->api;
        $this->context = $ZG->context;
        $this->ZG = $ZG;
    }

    /**
     * Отправляет сообщение.
     * Автоматически выбирает метод API (sendMessage, sendPhoto, sendMediaGroup
     * и т.д.)
     *
     * @param int|string|null $chat_id
     * @param int|null $message_thread_id
     *
     * @return array Результат запроса (или последнего запроса при
     *               последовательной отправке)
     *
     * @throws \JsonException
     * @throws \Amp\Http\Client\HttpException
     * @throws \ZenithGram\ZenithGram\Exceptions\MessageBuilderException
     * @see https://zenithgram.github.io/classes/messageMethods/send
     */
    public function send(int|string|null $chat_id = null, int|null $message_thread_id = null): array
    {
        if ($this->mediaPreviewUrl !== '') {
            $this->applyMediaPreview();
        }

        $params = ['chat_id' => $chat_id ?: $this->context->getChatId()];

        if ($message_thread_id !== null || $this->ZG->getMsgThreadId() !== null) {
            $params['message_thread_id'] = $message_thread_id;
        }

        if ($this->reply_markup_raw !== []) {
            $this->buildReplyMarkup();
        }

        $commonParams = array_merge($params, $this->additionally_params);

        if ($this->sendDice) {
            return $this->api->callAPI(
                'sendDice', array_merge($commonParams, $this->messageData),
            );
        }

        if ($this->sendSticker) {
            $stickerParams = [
                'sticker'      => $this->messageData['sticker'],
                'reply_markup' => $this->messageData['reply_markup'] ?? '',
            ];

            return $this->api->callAPI(
                'sendSticker', array_merge($commonParams, $stickerParams),
            );
        }

        $mediaCount = count($this->mediaQueue);

        if ($mediaCount === 0) {
            return $this->api->callAPI(
                'sendMessage', array_merge($commonParams, $this->messageData),
            );
        }

        $captionData = $this->messageData;
        $captionData['caption'] = $captionData['text'] ?? '';
        unset($captionData['text']);

        if ($mediaCount === 1) {
            return $this->sendSingleMedia(
                $this->mediaQueue[0], $commonParams, $captionData,
            );
        }

        if ($this->canBeGrouped($this->mediaQueue)) {
            $mediaGroupParams = $this->buildMediaGroupParams(
                $this->mediaQueue, $captionData,
            );

            return $this->api->callAPI(
                'sendMediaGroup', array_merge(
                $commonParams,
                $mediaGroupParams,
            ),
            );
        }

        throw new MessageBuilderException(
            "Вы добавили несовместимые типы медиа. Пример: нельзя отправлять одновременно несколько голосовых",
        );

    }

    /**
     * Редактирует текст существующего сообщения
     *
     * @param string|int|null $message_id
     * @param string|int|null $chat_id
     * @param int|null $message_thread_id
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/messageMethods/editText
     */

    public function editText(string|int|null $message_id = null,
        string|int|null $chat_id = null, int|null $message_thread_id = null
    ): array {
        $params = $this->getIdentifier($message_id, $chat_id, $message_thread_id);

        if ($this->reply_markup_raw !== []) {
            $this->buildReplyMarkup();
        }

        $commonParams = array_merge($params, $this->additionally_params);
        $commonParams += $this->messageData;


        return $this->api->callAPI('editMessageText', $commonParams);
    }

    /**
     * Редактирует описание существующего сообщения (обязательное наличие медиа
     * в сообщении)
     *
     * @param string|int|null $message_id
     * @param string|int|null $chat_id
     * @param int|null $message_thread_id
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/messageMethods/editCaption
     */
    public function editCaption(string|int|null $message_id = null,
        string|int|null $chat_id = null, int|null $message_thread_id = null
    ): array {
        $params = $this->getIdentifier($message_id, $chat_id, $message_thread_id);

        if ($this->reply_markup_raw !== []) {
            $this->buildReplyMarkup();
        }

        $this->messageData['caption'] = $this->messageData['text'];
        $this->messageData['caption_entities'] = $this->messageData['entities'];
        unset($this->messageData['text'], $this->messageData['entities']);

        $commonParams = array_merge($params, $this->additionally_params);
        $commonParams += $this->messageData;

        return $this->api->callAPI('editMessageCaption', $commonParams);
    }

    /**
     * Редактирует медиа существующего сообщения
     *
     * @param string|int|null $message_id
     * @param string|int|null $chat_id
     * @param int|null $message_thread_id
     *
     * @return array
     *
     * @throws \Exception
     *
     * @see https://zenithgram.github.io/classes/messageMethods/editCaption
     */
    public function editMedia(string|int|null $message_id = null,
        string|int|null $chat_id = null, int|null $message_thread_id = null
    ): array {
        $params = $this->getIdentifier($message_id, $chat_id, $message_thread_id);

        if (count($this->mediaQueue) !== 1) {
            throw new MessageBuilderException(
                "Для редактирования медиа (editMedia) нужно добавить новые файлы",
            );
        }

        if ($this->reply_markup_raw !== []) {
            $this->buildReplyMarkup();
        }

        $item = $this->mediaQueue[0];
        $inputMediaData = $this->prepareInputMediaForEdit($item);

        $params['media'] = $inputMediaData['media'];

        if (!empty($inputMediaData['attachments'])) {
            $params = array_merge($params, $inputMediaData['attachments']);
        }

        $finalParams = array_merge($params, $this->additionally_params);
        $finalParams += $this->messageData;

        return $this->api->callAPI('editMessageMedia', $finalParams);
    }

    private function getIdentifier(string|int|null $message_id,
        string|int|null $chat_id, int|null $message_thread_id
    ): array {
        $updateData = $this->context->getUpdateData();

        if (isset($updateData['callback_query']['inline_message_id'])) {
            return ['inline_message_id' => $updateData['callback_query']['inline_message_id']];
        }

        $params = [
            'chat_id'    => $chat_id ?: $this->context->getChatId(),
            'message_id' => $message_id ?: $this->context->getMessageId(),
        ];

        if ($message_thread_id !== null || $this->ZG->getMsgThreadId() !== null) {
            $params['message_thread_id'] = $message_thread_id;
        }

        return $params;
    }

    private function sendSingleMedia(array $item, array $commonParams,
        array $captionData,
    ): array {
        $type = $item['type'];
        $payload = $item['payload'];

        $method = 'send'.$this->mapTypeToApiMethod($type);
        $fieldName = $this->mapTypeToField($type);

        $params = array_merge($commonParams, $captionData);
        $params[$fieldName] = $payload;

        return $this->api->callAPI($method, $params);
    }

    private function canBeGrouped(array $queue): bool
    {
        if (empty($queue)) {
            return false;
        }

        foreach ($queue as $item) {
            if ($item['type'] === 'voice') {
                return false;
            }
        }

        $firstGroup = $this->getMediaTypeGroup($queue[0]['type']);

        foreach ($queue as $item) {
            if ($this->getMediaTypeGroup($item['type']) !== $firstGroup) {
                return false;
            }
        }

        return true;
    }

    private function getMediaTypeGroup(string $type): string
    {
        return match ($type) {
            'img', 'photo', 'video', 'animation' => 'visual',
            'audio' => 'audio',
            'document', 'doc' => 'document',
            default => 'unknown',
        };
    }

    private function prepareInputMediaForEdit(array $item): array
    {
        $originalType = $item['type'];

        $type = match ($originalType) {
            'img' => 'photo',
            'doc' => 'document',
            default => $originalType,
        };

        $inputMedia = ['type' => $type];
        $attachments = [];

        if ($item['payload'] instanceof LocalFile) {
            $attachKey = 'media_file_upload';
            $attachments[$attachKey] = $item['payload'];
            $inputMedia['media'] = 'attach://'.$attachKey;
        } else {
            $inputMedia['media'] = $item['payload'];
        }

        // Добавляем caption из текущего состояния объекта Message
        if (!empty($this->messageData['text'])) {
            $inputMedia['caption'] = $this->messageData['text'];
        }
        if (!empty($this->messageData['parse_mode'])) {
            $inputMedia['parse_mode'] = $this->messageData['parse_mode'];
        }
        if (!empty($this->messageData['entities'])) {
            $inputMedia['caption_entities'] = $this->messageData['entities'];
        }

        return ['media' => $inputMedia, 'attachments' => $attachments];
    }

    private function buildMediaGroupParams(array $queue, array $commonData,
    ): array {
        $mediaArray = [];
        $attachments = [];

        foreach ($queue as $index => $item) {
            $originalType = $item['type'];

            $type = match ($originalType) {
                'img' => 'photo',
                'animation' => 'video',
                default => $originalType,
            };

            $inputMedia = ['type' => $type];

            if ($item['payload'] instanceof LocalFile) {
                $attachKey = 'media_attach_'.$index;
                $attachments[$attachKey] = $item['payload'];
                $inputMedia['media'] = 'attach://'.$attachKey;
            } else {
                $inputMedia['media'] = $item['payload'];
            }

            if ($index === 0) {
                if (!empty($commonData['caption'])) {
                    $inputMedia['caption'] = $commonData['caption'];
                }
                if (!empty($commonData['parse_mode'])) {
                    $inputMedia['parse_mode'] = $commonData['parse_mode'];
                }
                if (!empty($commonData['entities'])) {
                    $inputMedia['caption_entities'] = $commonData['entities'];
                }
            }

            $mediaArray[] = $inputMedia;
        }

        return array_merge(['media' => $mediaArray], $attachments);
    }

    private function mapTypeToApiMethod(string $type): string
    {
        return match ($type) {
            'img', 'photo' => 'Photo',
            'animation' => 'Animation',
            'voice' => 'Voice',
            'audio' => 'Audio',
            'video' => 'Video',
            'document', 'doc' => 'Document',
            default => 'Document',
        };
    }

    private function mapTypeToField(string $type): string
    {
        return match ($type) {
            'img', 'photo' => 'photo',
            'animation' => 'animation',
            'voice' => 'voice',
            'audio' => 'audio',
            'video' => 'video',
            'document', 'doc' => 'document',
            default => 'document',
        };
    }

    private function buildReplyMarkup(): void
    {
        $is_inline = isset($this->reply_markup_raw['inline_keyboard']);
        $buttons = $is_inline ? $this->reply_markup_raw['inline_keyboard']
            : $this->reply_markup_raw['keyboard'];

        $searchBotButtons = false;
        foreach ($buttons as $row) {
            if (!is_array($row)) {
                throw new MessageBuilderException("Неправильный формат клавиатуры");
            }
            foreach ($row as $button) {
                if (is_string($button)) {
                    $searchBotButtons = true;
                    break;
                }
            }
        }

        if ($searchBotButtons) {
            $buttons = $this->findBotButtons($buttons, $is_inline);
            if ($is_inline) {
                $this->reply_markup_raw['inline_keyboard'] = $buttons;
            } else {
                $this->reply_markup_raw['keyboard'] = $buttons;
            }
        }

        $this->messageData['reply_markup'] = json_encode(
            $this->reply_markup_raw, JSON_THROW_ON_ERROR,
        );
    }

    private function findBotButtons(array $gettingButtons, bool $inline): array
    {
        $botButtons = $this->ZG->getBotButtons();

        foreach ($gettingButtons as $key => $row) {
            foreach ($row as $key_2 => $button) {
                if (is_string($button)) {
                    if (isset($botButtons[$button])) {
                        if ($inline) {
                            $gettingButtons[$key][$key_2]
                                = $this->ZG->buttonCallback(
                                $botButtons[$button], $button,
                            );
                        } else {
                            $gettingButtons[$key][$key_2]
                                = $this->ZG->buttonText($botButtons[$button]);
                        }
                    } else {
                        throw new MessageBuilderException(
                            "Не удалось найти кнопку $button",
                        );
                    }
                }
            }
        }

        return $gettingButtons;
    }

    private function applyMediaPreview(): void
    {
        $url = $this->mediaPreviewUrl;
        $invisibleCharacter = '​'; // U+200B

        if (($this->messageData['parse_mode'] ?? '')
            === MessageParseMode::MarkdownV2->value
            || ($this->messageData['parse_mode'] ?? '')
            === MessageParseMode::Markdown->value
        ) {
            $this->messageData['text'] = "[$invisibleCharacter](".$url.")"
                .($this->messageData['text'] ?? '');
        } elseif (($this->messageData['parse_mode'] ?? '')
            === MessageParseMode::HTML->value
        ) {
            $this->messageData['text'] = "<a href=\"".$url."\">"
                .$invisibleCharacter."</a>"
                .($this->messageData['text'] ?? '');
        } else {
            $this->messageData['text'] = $invisibleCharacter
                .($this->messageData['text'] ?? '');

            $lengthInUtf16 = strlen(
                    mb_convert_encoding(
                        $invisibleCharacter, 'UTF-16LE', 'UTF-8',
                    ),
                ) / 2;

            $entity = [
                'type'   => 'text_link',
                'offset' => 0,
                'length' => $lengthInUtf16,
                'url'    => $url,
            ];

            $currentEntities = [];
            if (isset($this->messageData['entities'])
                && is_string(
                    $this->messageData['entities'],
                )
            ) {
                $currentEntities = json_decode(
                    $this->messageData['entities'], true,
                ) ?? [];
            }

            array_unshift($currentEntities, $entity);
            $this->messageData['entities'] = json_encode($currentEntities);
        }
    }
}