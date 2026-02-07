<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Enums\InlineType;
use ZenithGram\ZenithGram\Enums\MessageParseMode;
use ZenithGram\ZenithGram\Inline;

class InlineTest extends TestCase
{
    /**
     * Тест базового типа Article.
     * Проверяем структуру input_message_content.
     */
    public function testCreateArticleBuilder(): void
    {
        $inline = new Inline(InlineType::Article);

        $result = $inline
            ->id('101')
            ->title('Test Title')
            ->description('Test Description')
            ->text('<b>Hello</b>')
            ->parseMode(MessageParseMode::HTML)
            ->create();

        $this->assertEquals('article', $result['type']);
        $this->assertEquals('101', $result['id']);
        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('Test Description', $result['description']);

        // Проверяем вложенную структуру сообщения
        $this->assertArrayHasKey('input_message_content', $result);
        $this->assertEquals('<b>Hello</b>', $result['input_message_content']['message_text']);
        $this->assertEquals('HTML', $result['input_message_content']['parse_mode']);
    }

    /**
     * Тест Photo с использованием URL.
     * Логика класса должна подставить ключ 'photo_url'.
     */
    public function testCreatePhotoWithUrl(): void
    {
        $inline = new Inline(InlineType::Photo);

        $result = $inline
            ->id('photo_1')
            ->fileUrl('https://example.com/image.jpg')
            ->thumb('https://example.com/thumb.jpg')
            ->text('Caption text') // Для фото text становится caption
            ->create();

        $this->assertEquals('photo', $result['type']);
        $this->assertEquals('https://example.com/image.jpg', $result['photo_url']);
        $this->assertEquals('https://example.com/thumb.jpg', $result['thumbnail_url']);
        $this->assertEquals('Caption text', $result['caption']);

        // Убеждаемся, что ID файла не попал в массив
        $this->assertArrayNotHasKey('photo_file_id', $result);
    }

    /**
     * Тест Photo с использованием File ID.
     * Логика класса должна подставить ключ 'photo_file_id'.
     */
    public function testCreatePhotoWithFileId(): void
    {
        $inline = new Inline(InlineType::Photo);

        $result = $inline
            ->id('photo_2')
            ->fileId('AgACAgIA...')
            ->create();

        $this->assertEquals('photo', $result['type']);
        $this->assertEquals('AgACAgIA...', $result['photo_file_id']);

        // Убеждаемся, что URL не попал в массив
        $this->assertArrayNotHasKey('photo_url', $result);
    }

    /**
     * Тест Video и дефолтных значений.
     * Проверяем mime_type по умолчанию.
     */
    public function testCreateVideoDefaults(): void
    {
        $inline = new Inline(InlineType::Video);

        $result = $inline
            ->id('vid_1')
            ->fileUrl('https://example.com/video.mp4')
            ->title('Video Title')
            ->create();

        $this->assertEquals('video', $result['type']);
        // В коде Inline.php прописано: $this->mimeType === '' ? 'video/mp4' : ...
        $this->assertEquals('video/mp4', $result['mime_type']);
    }

    /**
     * Тест клавиатуры.
     * Проверяем, что массив кнопок оборачивается в ['inline_keyboard' => ...]
     */
    public function testKeyboardStructure(): void
    {
        $buttons = [
            [['text' => 'btn1', 'callback_data' => '1']],
        ];

        $inline = new Inline(InlineType::Article);
        $result = $inline
            ->text('Menu')
            ->kbd($buttons)
            ->create();

        $this->assertArrayHasKey('reply_markup', $result);
        $this->assertEquals($buttons, $result['reply_markup']['inline_keyboard']);
    }

    /**
     * Тест дополнительных параметров.
     * Проверяем merge массивов.
     */
    public function testAdditionalParams(): void
    {
        $inline = new Inline(InlineType::Article);

        $result = $inline
            ->text('Text')
            ->params(['custom_field' => 123, 'reply_width' => 50])
            ->create();

        // Проверяем, что параметры попали в input_message_content (для Article)
        // Внимание: в коде Inline.php для Article params_additionally добавляются к message,
        // а потом message кладется в input_message_content.
        $this->assertEquals(123, $result['input_message_content']['custom_field']);
        $this->assertEquals(50, $result['input_message_content']['reply_width']);
    }

    /**
     * Тест Location.
     */
    public function testLocation(): void
    {
        $inline = new Inline(InlineType::Location);

        $result = $inline
            ->coordinates(55.75, 37.61)
            ->title('Moscow')
            ->create();

        $this->assertEquals('location', $result['type']);
        $this->assertEquals(55.75, $result['latitude']);
        $this->assertEquals(37.61, $result['longitude']);
    }
    public function testCreateGif(): void
    {
        $inline = new Inline(InlineType::Gif);
        $result = $inline->id('gif_1')->fileId('123')->create();

        $this->assertEquals('gif', $result['type']);
        $this->assertEquals('123', $result['gif_file_id']);
    }

    public function testCreateMpeg4Gif(): void
    {
        $inline = new Inline(InlineType::Mpeg4Gif);
        $result = $inline->id('mpeg_1')->fileUrl('http://url.com/a.mp4')->create();

        $this->assertEquals('mpeg4_gif', $result['type']);
        $this->assertEquals('http://url.com/a.mp4', $result['mpeg4_url']);
    }

    public function testCreateAudio(): void
    {
        $inline = new Inline(InlineType::Audio);
        $result = $inline->id('audio_1')->fileId('123')->create();

        $this->assertEquals('audio', $result['type']);
        $this->assertEquals('123', $result['audio_file_id']);
    }

    public function testCreateVoice(): void
    {
        $inline = new Inline(InlineType::Voice);
        $result = $inline->id('voice_1')->fileId('123')->create();

        $this->assertEquals('voice', $result['type']);
        $this->assertEquals('123', $result['voice_file_id']);
    }

    public function testCreateDocument(): void
    {
        $inline = new Inline(InlineType::Document);
        $result = $inline->id('doc_1')->fileId('123')->mimeType('application/pdf')->create();

        $this->assertEquals('document', $result['type']);
        $this->assertEquals('123', $result['document_file_id']);
        $this->assertEquals('application/pdf', $result['mime_type']);
    }

    public function testCreateVenue(): void
    {
        $inline = new Inline(InlineType::Venue);
        $result = $inline
            ->coordinates(50.0, 50.0)
            ->title('Home')
            ->address('Baker St')
            ->create();

        $this->assertEquals('venue', $result['type']);
        $this->assertEquals(50.0, $result['latitude']);
        $this->assertEquals('Home', $result['title']);
        $this->assertEquals('Baker St', $result['address']);
    }
}