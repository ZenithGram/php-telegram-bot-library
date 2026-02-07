<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\UpdateContext;

class UpdateContextTest extends TestCase
{
    /**
     * Тест обычного текстового сообщения
     */
    public function testTextMessageExtraction(): void
    {
        $updateData = [
            'update_id' => 1001,
            'message' => [
                'message_id' => 55,
                'from' => [
                    'id' => 123456,
                    'is_bot' => false,
                    'first_name' => 'John'
                ],
                'chat' => [
                    'id' => 987654,
                    'type' => 'private'
                ],
                'date' => 1600000000,
                'text' => 'Hello World'
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals('text', $ctx->getType());
        $this->assertEquals(987654, $ctx->getChatId());
        $this->assertEquals(123456, $ctx->getUserId());
        $this->assertEquals(55, $ctx->getMessageId());
        $this->assertEquals('Hello World', $ctx->getText());
        $this->assertNull($ctx->getCallbackData());
    }

    /**
     * Тест нажатия на Inline-кнопку (CallbackQuery)
     */
    public function testCallbackQueryExtraction(): void
    {
        $updateData = [
            'update_id' => 1002,
            'callback_query' => [
                'id' => '777',
                'from' => [
                    'id' => 111,
                    'first_name' => 'Alice'
                ],
                'message' => [
                    'message_id' => 88,
                    'chat' => [
                        'id' => 222,
                        'type' => 'group'
                    ],
                    'text' => 'Menu Message'
                ],
                'chat_instance' => '333',
                'data' => 'menu_btn_click'
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals('callback_query', $ctx->getType());
        $this->assertEquals('777', $ctx->getQueryId());
        $this->assertEquals('menu_btn_click', $ctx->getCallbackData());

// Проверяем, что ID берутся из вложенного message или callback_query
        $this->assertEquals(222, $ctx->getChatId());
        $this->assertEquals(111, $ctx->getUserId());
        $this->assertEquals(88, $ctx->getMessageId()); // ID сообщения, к которому привязана кнопка
    }

    /**
     * Тест команды бота (entity type = bot_command)
     */
    public function testBotCommandDetection(): void
    {
        $updateData = [
            'update_id' => 1003,
            'message' => [
                'message_id' => 101,
                'from' => ['id' => 10],
                'chat' => ['id' => 20],
                'text' => '/start',
                'entities' => [
                    [
                        'offset' => 0,
                        'length' => 6,
                        'type' => 'bot_command'
                    ]
                ]
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals('bot_command', $ctx->getType());
        $this->assertEquals('/start', $ctx->getText());
    }

    /**
     * Тест отредактированного сообщения
     */
    public function testEditedMessageExtraction(): void
    {
        $updateData = [
            'update_id' => 1004,
            'edited_message' => [
                'message_id' => 202,
                'from' => ['id' => 555],
                'chat' => ['id' => 777],
                'date' => 1600000050,
                'edit_date' => 1600000060,
                'text' => 'Edited text'
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals('edited_message', $ctx->getType());
        $this->assertEquals('Edited text', $ctx->getText());
        $this->assertEquals(777, $ctx->getChatId());
        $this->assertEquals(555, $ctx->getUserId());
        $this->assertEquals(202, $ctx->getMessageId());
    }

    /**
     * Тест Inline Query (ввод текста в поле ввода через @bot)
     */
    public function testInlineQueryExtraction(): void
    {
        $updateData = [
            'update_id' => 1005,
            'inline_query' => [
                'id' => '9999',
                'from' => [
                    'id' => 444,
                    'first_name' => 'Bob'
                ],
                'query' => 'search query',
                'offset' => ''
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals('inline_query', $ctx->getType());
        $this->assertEquals('search query', $ctx->getText());
        $this->assertEquals('9999', $ctx->getQueryId());
        $this->assertEquals(444, $ctx->getUserId());

        $this->assertNull($ctx->getChatId());
        $this->assertNull($ctx->getMessageId());
    }

    /**
     * Тест получения данных об ответе на сообщение (Reply)
     */
    public function testReplyExtraction(): void
    {
        $updateData = [
            'message' => [
                'message_id' => 500,
                'from' => ['id' => 100],
                'chat' => ['id' => 200],
                'text' => 'Reply msg',
                'reply_to_message' => [
                    'message_id' => 499,
                    'from' => ['id' => 101],
                    'text' => 'Original text'
                ]
            ]
        ];

        $ctx = new UpdateContext($updateData);

        $this->assertEquals(499, $ctx->getReplyMessageId());
        $this->assertEquals(101, $ctx->getReplyUserId());
        $this->assertEquals('Original text', $ctx->getReplyText());
    }

    /**
     * Тест приоритета получения текста (caption для медиа)
     */
    public function testCaptionExtraction(): void
    {
        $updateData = [
            'message' => [
                'message_id' => 600,
                'chat' => ['id' => 1],
                'photo' => [],
                'caption' => 'Photo description'
            ]
        ];

        $ctx = new UpdateContext($updateData);
// Метод getText() должен вернуть caption, если text отсутствует
        $this->assertEquals('Photo description', $ctx->getText());
        $this->assertEquals('text', $ctx->getType()); // В коде тип 'text' возвращается, если есть message, даже с фото, если нет bot_command
    }
}