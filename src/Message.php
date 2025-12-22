<?php

namespace ZenithGram\ZenithGram;

use ZenithGram\ZenithGram\Utils\LocalFile;

final class Message
{
    use MessageBuilderTrait;

    private ApiClient $api;
    private UpdateContext $context;
    private ZG $ZG;

    public function __construct(?string $text, ZG $ZG)
    {
        $this->messageData['text'] = $text;
        $this->messageData['parse_mode'] = $ZG->parseModeDefault->value;
        $this->api = $ZG->api;
        $this->context = $ZG->context;
        $this->ZG = $ZG;
    }

    /**
     * Отправляет сообщение.
     * Автоматически выбирает метод API (sendMessage, sendPhoto, sendMediaGroup и т.д.)
     *
     * @param int|string|null $chat_id
     *
     * @return array Результат запроса (или последнего запроса при последовательной отправке)
     *
     * @throws \JsonException
     * @see https://zenithgram.github.io/classes/messageMethods/send
     */
    public function send(int|string|null $chat_id = null): array
    {
        if ($this->mediaPreviewUrl !== '') {
            $this->applyMediaPreview();
        }

        $chat_id = $chat_id ?? $this->context->getChatId();
        $params = ['chat_id' => $chat_id];

        // 1. Клавиатура
        if ($this->reply_markup_raw !== []) {
            $this->buildReplyMarkup();
        }

        // 2. Доп. параметры
        $commonParams = array_merge($params, $this->additionally_params);

        // --- Спец. типы: Dice, Sticker ---
        if ($this->sendDice) {
            return $this->api->callAPI('sendDice', array_merge($commonParams, $this->messageData));
        }

        if ($this->sendSticker) {
            $stickerParams = [
                'sticker' => $this->messageData['sticker'],
                'reply_markup' => $this->messageData['reply_markup'] ?? ''
            ];
            return $this->api->callAPI('sendSticker', array_merge($commonParams, $stickerParams));
        }

        $mediaCount = count($this->mediaQueue);

        // --- Просто текст ---
        if ($mediaCount === 0) {
            return $this->api->callAPI('sendMessage', array_merge($commonParams, $this->messageData));
        }

        // Подготовка данных для медиа (caption вместо text)
        $captionData = $this->messageData;
        $captionData['caption'] = $captionData['text'] ?? '';
        unset($captionData['text']);

        // --- Одиночное медиа ---
        if ($mediaCount === 1) {
            return $this->sendSingleMedia($this->mediaQueue[0], $commonParams, $captionData);
        }

        // --- Несколько медиа ---

        // Проверяем, можно ли это отправить группой (sendMediaGroup)
        if ($this->canBeGrouped($this->mediaQueue)) {
            $mediaGroupParams = $this->buildMediaGroupParams($this->mediaQueue, $captionData);

            // reply_markup не поддерживается в sendMediaGroup, но передаем params на случай reply_to_message_id
            return $this->api->callAPI('sendMediaGroup', array_merge(
                $commonParams,
                $mediaGroupParams
            ));
        }

        throw new \LogicException( "Вы добавили несовместимые типы медиа. Пример: нельзя отправлять одновременно несколько голосовых");

    }

    /**
     * Отправляет один медиа-файл соответствующим методом
     */
    private function sendSingleMedia(array $item, array $commonParams, array $captionData): array
    {
        $type = $item['type'];
        $payload = $item['payload'];

        $method = 'send' . $this->mapTypeToApiMethod($type);
        $fieldName = $this->mapTypeToField($type);

        $params = array_merge($commonParams, $captionData);
        $params[$fieldName] = $payload;

        return $this->api->callAPI($method, $params);
    }

    /**
     * Проверяет валидность группировки медиа.
     * Voice нельзя группировать. Нельзя смешивать Audio с Photo и т.д.
     */
    private function canBeGrouped(array $queue): bool
    {
        if (empty($queue)) {
            return false;
        }

        // Voice категорически нельзя в группу
        foreach ($queue as $item) {
            if ($item['type'] === 'voice') {
                return false;
            }
        }

        // Определяем категорию первого элемента
        $firstGroup = $this->getMediaTypeGroup($queue[0]['type']);

        // Все остальные элементы должны быть той же категории
        foreach ($queue as $item) {
            if ($this->getMediaTypeGroup($item['type']) !== $firstGroup) {
                return false;
            }
        }

        return true;
    }

    /**
     * Определяет группу совместимости типов для sendMediaGroup
     */
    private function getMediaTypeGroup(string $type): string
    {
        return match ($type) {
            // Визуальные медиа (можно смешивать фото и видео)
            // GIF ('animation') в группе отправляется как 'video'
            'img', 'photo', 'video', 'animation' => 'visual',
            'audio' => 'audio',
            'document', 'doc' => 'document',
            default => 'unknown',
        };
    }

    /**
     * Собирает параметры для sendMediaGroup
     */
    private function buildMediaGroupParams(array $queue, array $commonData): array
    {
        $mediaArray = [];
        $attachments = [];

        foreach ($queue as $index => $item) {
            $originalType = $item['type'];

            // Корректировка типа для группы
            $type = match ($originalType) {
                'img' => 'photo',
                'animation' => 'video', // GIF в группе должен быть video
                default => $originalType,
            };

            $inputMedia = ['type' => $type];

            if ($item['payload'] instanceof LocalFile) {
                $attachKey = 'media_attach_' . $index;
                $attachments[$attachKey] = $item['payload'];
                $inputMedia['media'] = 'attach://' . $attachKey;
            } else {
                $inputMedia['media'] = $item['payload'];
            }

            // Caption только к первому элементу
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
            'animation'    => 'Animation', // Для одиночной отправки используем sendAnimation
            'voice'        => 'Voice',
            'audio'        => 'Audio',
            'video'        => 'Video',
            'document', 'doc' => 'Document',
            default        => 'Document',
        };
    }

    private function mapTypeToField(string $type): string
    {
        return match ($type) {
            'img', 'photo' => 'photo',
            'animation'    => 'animation',
            'voice'        => 'voice',
            'audio'        => 'audio',
            'video'        => 'video',
            'document', 'doc' => 'document',
            default        => 'document',
        };
    }

    private function buildReplyMarkup(): void
    {
        $is_inline = isset($this->reply_markup_raw['inline_keyboard']);
        $buttons = $is_inline ? $this->reply_markup_raw['inline_keyboard'] : $this->reply_markup_raw['keyboard'];

        $searchBotButtons = false;
        foreach ($buttons as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException("Неправильный формат клавиатуры");
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

        $this->messageData['reply_markup'] = json_encode($this->reply_markup_raw, JSON_THROW_ON_ERROR);
    }

    private function findBotButtons(array $gettingButtons, bool $inline): array
    {
        $botButtons = $this->ZG->getBotButtons();

        foreach ($gettingButtons as $key => $row) {
            foreach ($row as $key_2 => $button) {
                if (is_string($button)) {
                    if (isset($botButtons[$button])) {
                        if ($inline) {
                            $gettingButtons[$key][$key_2] = $this->ZG->buttonCallback($botButtons[$button], $button);
                        } else {
                            $gettingButtons[$key][$key_2] = $this->ZG->buttonText($botButtons[$button]);
                        }
                    } else {
                        throw new \RuntimeException("Не удалось найти кнопку $button");
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

        if (($this->messageData['parse_mode'] ?? '') === MessageParseMode::MarkdownV2->value
            || ($this->messageData['parse_mode'] ?? '') === MessageParseMode::Markdown->value
        ) {
            $this->messageData['text'] = "[$invisibleCharacter](".$url.")".($this->messageData['text'] ?? '');
        } elseif (($this->messageData['parse_mode'] ?? '') === MessageParseMode::HTML->value) {
            $this->messageData['text'] = "<a href=\"".$url."\">".$invisibleCharacter."</a>"
                .($this->messageData['text'] ?? '');
        } else {
            $this->messageData['text'] = $invisibleCharacter.($this->messageData['text'] ?? '');

            $lengthInUtf16 = strlen(mb_convert_encoding($invisibleCharacter, 'UTF-16LE', 'UTF-8')) / 2;

            $entity = [
                'type'   => 'text_link',
                'offset' => 0,
                'length' => $lengthInUtf16,
                'url'    => $url,
            ];


            $currentEntities = [];
            if (isset($this->messageData['entities']) && is_string($this->messageData['entities'])) {
                $currentEntities = json_decode($this->messageData['entities'], true) ?? [];
            }

            array_unshift($currentEntities, $entity);
            $this->messageData['entities'] = json_encode($currentEntities);
        }
    }
}