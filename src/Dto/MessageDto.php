<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Dto;

/**
 * DTO для объекта Message из Telegram API.
 *
 * Представляет собой сообщение. Это может быть текстовое сообщение,
 * медиафайл, системное сообщение и т.д.
 *
 * @see https://core.telegram.org/bots/api#message
 */
class MessageDto
{
    public function __construct(
        public readonly int $messageId,
        public readonly int $date,
        public readonly ChatDto $chat,

        // Опциональные поля (могут отсутствовать, например, в каналах нет 'from')
        public readonly ?int $messageThreadId,
        public readonly ?UserDto $from,
        public readonly ?ChatDto $senderChat,

        // Рекурсивная вложенность (ответ на сообщение)
        public readonly ?MessageDto $replyToMessage,
        public readonly ?MessageDto $pinnedMessage,

        // Контент
        public readonly ?string $text,
        public readonly ?string $caption,

        // Массив сущностей (ссылки, жирный текст и т.д.)
        public readonly ?array $entities,
        public readonly ?array $captionEntities,

        // Медиа и специальные типы
        public readonly ?array $dice, // ['emoji' => '🎲', 'value' => 6]
        public readonly ?array $photo, // Массив PhotoSize
        public readonly ?array $sticker,
        public readonly ?array $video,
        public readonly ?array $audio,
        public readonly ?array $voice,
        public readonly ?array $document,

        // Служебные данные
        public readonly ?bool $isTopicMessage,
        public readonly ?array $newChatMembers, // Массив UserDto
        public readonly ?UserDto $leftChatMember,
    ) {}

    /**
     * Фабричный метод для создания DTO из массива.
     *
     * @param array $data Массив данных сообщения (обычно $update['message'])
     * @return static
     */
    public static function fromArray(array $data): static
    {
        // Обработка вложенных массивов UserDto для новых участников
        $newChatMembers = [];
        if (isset($data['new_chat_members']) && is_array($data['new_chat_members'])) {
            foreach ($data['new_chat_members'] as $member) {
                $newChatMembers[] = UserDto::fromArray($member);
            }
        }

        return new static(
            messageId:       $data['message_id'],
            date:            $data['date'],

            // ChatDto обязателен в объекте Message
            chat:            ChatDto::fromArray($data['chat']),

            // Опциональные поля
            messageThreadId: $data['message_thread_id'] ?? null,
            from:            isset($data['from']) ? UserDto::fromArray($data['from']) : null,
            senderChat:      isset($data['sender_chat']) ? ChatDto::fromArray($data['sender_chat']) : null,

            // РЕКУРСИЯ: Создаем новый MessageDto, если это ответ на сообщение
            replyToMessage:  isset($data['reply_to_message']) ? self::fromArray($data['reply_to_message']) : null,
            pinnedMessage:   isset($data['pinned_message']) ? self::fromArray($data['pinned_message']) : null,

            // Текст и подписи
            text:            $data['text'] ?? null,
            caption:         $data['caption'] ?? null,

            // Сущности (можно сделать отдельный EntityDto, но пока оставим массивом)
            entities:        $data['entities'] ?? null,
            captionEntities: $data['caption_entities'] ?? null,

            // Медиа
            dice:            $data['dice'] ?? null,
            photo:           $data['photo'] ?? null,
            sticker:         $data['sticker'] ?? null,
            video:           $data['video'] ?? null,
            audio:           $data['audio'] ?? null,
            voice:           $data['voice'] ?? null,
            document:        $data['document'] ?? null,

            // Служебные
            isTopicMessage:  $data['is_topic_message'] ?? null,
            newChatMembers:  !empty($newChatMembers) ? $newChatMembers : null,
            leftChatMember:  isset($data['left_chat_member']) ? UserDto::fromArray($data['left_chat_member']) : null,
        );
    }

    /**
     * Удобный хелпер для получения чистого текста (из text или caption)
     */
    public function getEffectiveText(): ?string
    {
        return $this->text ?? $this->caption;
    }

    /**
     * Проверка, является ли сообщение ответом
     */
    public function isReply(): bool
    {
        return $this->replyToMessage !== null;
    }

    /**
     * Проверка на наличие Dice (используя ваш Enum)
     */
    public function getDiceEmoji(): ?string
    {
        return $this->dice['emoji'] ?? null;
    }
}