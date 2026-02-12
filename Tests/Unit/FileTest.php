<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\File;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;

class FileTest extends TestCase
{
    /**
     * Тест статического метода getFileId.
     * Проверяем извлечение ID из разных типов сообщений.
     */
    public function testGetFileIdFromDifferentTypes(): void
    {
// 1. Фото (должен взять последний элемент - самое лучшее качество)
        $photoContext = [
            'message' => [
                'photo' => [
                    ['file_id' => 'small_id', 'file_size' => 100],
                    ['file_id' => 'medium_id', 'file_size' => 500],
                    ['file_id' => 'large_id', 'file_size' => 1000],
                ]
            ]
        ];
        $this->assertEquals('large_id', File::getFileId($photoContext));

// 2. Документ
        $docContext = [
            'message' => [
                'document' => ['file_id' => 'doc_123']
            ]
        ];
        $this->assertEquals('doc_123', File::getFileId($docContext));

// 3. Голосовое сообщение
        $voiceContext = [
            'message' => [
                'voice' => ['file_id' => 'voice_777']
            ]
        ];
        $this->assertEquals('voice_777', File::getFileId($voiceContext));

// 4. Поиск конкретного типа
        $mixedContext = [
            'message' => [
                'photo' => [['file_id' => 'p1']],
                'video' => ['file_id' => 'v1']
            ]
        ];
        $this->assertEquals('v1', File::getFileId($mixedContext, 'video'));
    }

    /**
     * Тест расчета размера файла в разных единицах.
     */
    public function testGetFileSizeConversion(): void
    {
        $apiMock = $this->createMock(ApiClientInterface::class);

// Мокаем ответ getFile от Telegram API
        $apiMock->method('callAPI')
            ->with('getFile', ['file_id' => 'test_file'])
            ->willReturn([
                'result' => [
                    'file_id' => 'test_file',
                    'file_size' => 1048576, // Ровно 1 МБ в байтах
                    'file_path' => 'docs/file.pdf'
                ]
            ]);

        $file = new File('test_file', $apiMock);

// Проверка в байтах (по умолчанию)
        $this->assertEquals(1048576, $file->getFileSize());

// Проверка в Килобайтах
        $this->assertEquals(1024, $file->getFileSize('KB'));

// Проверка в Мегабайтах
        $this->assertEquals(1, $file->getFileSize('MB'));
    }

    /**
     * Тест получения полного URL пути к файлу.
     */
    public function testGetFilePath(): void
    {
        $apiMock = $this->createMock(ApiClientInterface::class);

// Настраиваем мок API клиента
        $apiMock->method('getApiFileUrl')
            ->willReturn('https://api.telegram.org/file/bot123:TOKEN/');

        $apiMock->method('callAPI')
            ->willReturn([
                'result' => ['file_path' => 'photos/image_0.jpg']
            ]);

        $file = new File('some_id', $apiMock);

        $expectedUrl = 'https://api.telegram.org/file/bot123:TOKEN/photos/image_0.jpg';
        $this->assertEquals($expectedUrl, $file->getFilePath());
    }

    /**
     * Тест выброса исключения при слишком большом размере (ограничение Telegram API для ботов - 20МБ)
     */
    public function testSaveThrowsExceptionOnLargeFile(): void
    {
        $apiMock = $this->createMock(ApiClientInterface::class);

        $apiMock->method('callAPI')
            ->willReturn([
                'result' => [
                    'file_size' => 25 * 1024 * 1024 // 25 MB
                ]
            ]);

        $file = new File('big_file', $apiMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Размер файла превышает 20 МБ');

        $file->save('/tmp/test');
    }
}
