<?php
declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Dto\MessageDto;
use ZenithGram\ZenithGram\Dto\ChatDto;
use ZenithGram\ZenithGram\Dto\UserDto;

class MessageDtoTest extends TestCase
{
    /**
     * Тест создания DTO из массива с минимальным набором данных
     */
    public function testFromArrayMinimal(): void
    {
        $data = [
            'message_id' => 123,
            'date' => 1672531200,
            'chat' => [
                'id' => 999,
                'type' => 'private',
                'first_name' => 'John'
            ]
        ];

        $dto = MessageDto::fromArray($data);

        $this->assertEquals(123, $dto->messageId);
        $this->assertEquals(1672531200, $dto->date);

        $this->assertInstanceOf(ChatDto::class, $dto->chat);
        $this->assertEquals(999, $dto->chat->id);
        $this->assertEquals('private', $dto->chat->type);

        $this->assertNull($dto->text);
        $this->assertNull($dto->from);
        $this->assertNull($dto->replyToMessage);
    }

    /**
     * Тест создания DTO с полным набором данных (Text, User, Entities)
     */
    public function testFromArrayFullText(): void
    {
        $data = [
            'message_id' => 456,
            'date' => 1672531500,
            'chat' => ['id' => 100, 'type' => 'group', 'title' => 'My Group'],
            'from' => [
                'id' => 555,
                'is_bot' => false,
                'first_name' => 'Alice',
                'username' => 'alice_wonder'
            ],
            'text' => '/start',
            'entities' => [
                ['type' => 'bot_command', 'offset' => 0, 'length' => 6]
            ]
        ];

        $dto = MessageDto::fromArray($data);

        $this->assertInstanceOf(UserDto::class, $dto->from);
        $this->assertEquals(555, $dto->from->id);
        $this->assertEquals('alice_wonder', $dto->from->username);

        $this->assertEquals('/start', $dto->text);
        $this->assertIsArray($dto->entities);
        $this->assertEquals('bot_command', $dto->entities[0]['type']);
    }

    /**
     * Тест рекурсивной вложенности (ответ на сообщение - reply_to_message)
     */
    public function testReplyToMessageRecursion(): void
    {
        $data = [
            'message_id' => 200,
            'date' => 1672532000,
            'chat' => ['id' => 1, 'type' => 'private'],
            'text' => 'This is a reply',
            'reply_to_message' => [
                'message_id' => 199,
                'date' => 1672531900,
                'chat' => ['id' => 1, 'type' => 'private'],
                'text' => 'Original message',
                'from' => ['id' => 2, 'is_bot' => false, 'first_name' => 'Bob']
            ]
        ];

        $dto = MessageDto::fromArray($data);

        $this->assertTrue($dto->isReply());
        $this->assertInstanceOf(MessageDto::class, $dto->replyToMessage);

// Проверяем данные родительского сообщения
        $reply = $dto->replyToMessage;
        $this->assertEquals(199, $reply->messageId);
        $this->assertEquals('Original message', $reply->text);
        $this->assertEquals(2, $reply->from->id);

// У родителя не должно быть ответа (в данном тесте)
        $this->assertFalse($reply->isReply());
        $this->assertNull($reply->replyToMessage);
    }

    /**
     * Тест хелпера getEffectiveText (text или caption)
     */
    public function testGetEffectiveText(): void
    {
// 1. Только текст
        $msgWithText = MessageDto::fromArray([
            'message_id' => 1, 'date' => 0, 'chat' => ['id' => 1, 'type' => 'a'],
            'text' => 'Just text'
        ]);
        $this->assertEquals('Just text', $msgWithText->getEffectiveText());

// 2. Только подпись (caption)
        $msgWithCaption = MessageDto::fromArray([
            'message_id' => 2, 'date' => 0, 'chat' => ['id' => 1, 'type' => 'a'],
            'caption' => 'Photo caption',
            'photo' => []
        ]);
        $this->assertEquals('Photo caption', $msgWithCaption->getEffectiveText());

// 3. Ничего
        $msgEmpty = MessageDto::fromArray([
            'message_id' => 3, 'date' => 0, 'chat' => ['id' => 1, 'type' => 'a'],
            'sticker' => []
        ]);
        $this->assertNull($msgEmpty->getEffectiveText());
    }

    /**
     * Тест новых участников чата (массив объектов UserDto)
     */
    public function testNewChatMembers(): void
    {
        $data = [
            'message_id' => 300,
            'date' => 0,
            'chat' => ['id' => 500, 'type' => 'group'],
            'new_chat_members' => [
                ['id' => 10, 'is_bot' => false, 'first_name' => 'User1'],
                ['id' => 11, 'is_bot' => true, 'first_name' => 'Bot2']
            ]
        ];

        $dto = MessageDto::fromArray($data);

        $this->assertIsArray($dto->newChatMembers);
        $this->assertCount(2, $dto->newChatMembers);
        $this->assertInstanceOf(UserDto::class, $dto->newChatMembers[0]);
        $this->assertInstanceOf(UserDto::class, $dto->newChatMembers[1]);

        $this->assertEquals(10, $dto->newChatMembers[0]->id);
        $this->assertEquals(11, $dto->newChatMembers[1]->id);
        $this->assertTrue($dto->newChatMembers[1]->isBot);
    }

    /**
     * Тест получения эмодзи из кубика (Dice)
     */
    public function testDiceExtraction(): void
    {
        $data = [
            'message_id' => 400,
            'date' => 0,
            'chat' => ['id' => 1, 'type' => 'p'],
            'dice' => ['emoji' => '🎲', 'value' => 6]
        ];

        $dto = MessageDto::fromArray($data);

        $this->assertEquals('🎲', $dto->getDiceEmoji());
        $this->assertEquals(6, $dto->dice['value']);
    }
}
