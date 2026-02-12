<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Button;

class ButtonTest extends TestCase
{
    /**
     * Тест кнопки с Callback Data (Inline)
     */
    public function testCallbackButton(): void
    {
        $btn = Button::cb('Click Me', 'action_123');

        $this->assertEquals([
            'text' => 'Click Me',
            'callback_data' => 'action_123'
        ], $btn);
    }

    /**
     * Тест кнопки-ссылки (Inline)
     */
    public function testUrlButton(): void
    {
        $btn = Button::url('Google', 'https://google.com');

        $this->assertEquals([
            'text' => 'Google',
            'url' => 'https://google.com'
        ], $btn);
    }

    /**
     * Тест кнопки WebApp (Inline)
     */
    public function testWebAppButton(): void
    {
        $btn = Button::webApp('Open App', 'https://myapp.com');

        $this->assertEquals([
            'text' => 'Open App',
            'web_app' => ['url' => 'https://myapp.com']
        ], $btn);
    }

    /**
     * Тест обычной текстовой кнопки (Reply)
     */
    public function testTextButton(): void
    {
        $btn = Button::text('Menu');

        $this->assertEquals(['text' => 'Menu'], $btn);
    }

    /**
     * Тест кнопки запроса контакта (Reply)
     */
    public function testContactButton(): void
    {
        $btn = Button::contact('Send Phone');

        $this->assertEquals([
            'text' => 'Send Phone',
            'request_contact' => true
        ], $btn);
    }

    /**
     * Тест кнопки запроса геолокации (Reply)
     */
    public function testLocationButton(): void
    {
        $btn = Button::location('Send Location');

        $this->assertEquals([
            'text' => 'Send Location',
            'request_location' => true
        ], $btn);
    }
}
