<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Action;
use ZenithGram\ZenithGram\Enums\MessageParseMode;

class ActionTest extends TestCase
{
    /**
     * Тест базовых свойств: ID и условие
     */
    public function testConstructorAndGetters(): void
    {
        $action = new Action('test_id', '/start');

        $this->assertEquals('test_id', $action->getId());
        $this->assertEquals('/start', $action->getCondition());
    }

    /**
     * Тест установки обработчика (func)
     */
    public function testFuncHandler(): void
    {
        $action = new Action('id', 'cond');
        $callback = function() { return 'hello'; };

        $action->func($callback);

        $handler = $action->getHandler();
        $this->assertIsCallable($handler);
        $this->assertEquals('hello', $handler());
    }

    /**
     * Тест логики ограничений доступа (Access / NoAccess)
     */
    public function testAccessLogic(): void
    {
        $action = new Action('id', 'cond');
        $handler = function() {};

// Настройка разрешенных ID
        $action->access([123, 456], $handler);

        $this->assertEquals([123, 456], $action->getAccessIds());
        $this->assertSame($handler, $action->getAccessHandler());

// Настройка запрещенных ID
        $action->noAccess(777); // Передаем одно число вместо массива
        $this->assertEquals([777], $action->getNoAccessIds());
    }

    /**
     * Тест работы MessageBuilderTrait внутри Action.
     * Проверяем, как заполняется массив messageData.
     */
    public function testMessageBuilderTraitIntegration(): void
    {
        $action = new Action('id', 'cond');

        $action->text('Hello')
            ->parseMode(MessageParseMode::HTML)
            ->reply(12345);

        $data = $action->getMessageData();

        $this->assertEquals('Hello', $data['text']);
        $this->assertEquals('HTML', $data['parse_mode']);
        $this->assertEquals(12345, $data['reply_to_message_id']);
    }

    /**
     * Тест методов редактирования сообщения.
     * Проверяем смену messageDataAction (0 - send, 1 - editText, 2 - editCaption, 3 - editMedia)
     */
    public function testEditActions(): void
    {
        $action = new Action('id', 'cond');

// По умолчанию 0 (отправка)
        $this->assertEquals(0, $action->getMessageDataAction());

// Редактирование текста
        $action->editText('new text');
        $this->assertEquals(1, $action->getMessageDataAction());
        $this->assertEquals('new text', $action->getMessageData()['text']);

// Редактирование подписи
        $action->editCaption('new caption');
        $this->assertEquals(2, $action->getMessageDataAction());

// Редактирование медиа
        $action->editMedia();
        $this->assertEquals(3, $action->getMessageDataAction());
    }

    /**
     * Тест редиректа и query (всплывашки)
     */
    public function testRedirectAndQuery(): void
    {
        $action = new Action('id', 'cond');

        $action->redirect('target_route_id')
            ->query('Processing...');

        $this->assertEquals('target_route_id', $action->redirect_to);
        $this->assertEquals('Processing...', $action->getQueryText());
    }

    /**
     * Тест middleware
     */
    public function testMiddleware(): void
    {
        $action = new Action('id', 'cond');
        $middleware = function($tg, $next) { $next(); };

        $action->middleware($middleware);

        $this->assertNotNull($action->middleware_handler);
        $this->assertInstanceOf(\Closure::class, $action->middleware_handler);
    }
}
