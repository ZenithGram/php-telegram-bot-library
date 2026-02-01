<?php

namespace ZenithGram\ZenithGram;

use Amp\File as AmpFile;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;

class File
{
    private array $file_info = [];

    private const MAX_DOWNLOAD_SIZE_BYTES = 20971520;

    public function __construct(
        private readonly string $file_id,
        public readonly ApiClientInterface $api,
    ) {}


    public function getFileInfo(): array
    {
        if (empty($this->file_info)) {
            $response = $this->api->callAPI(
                'getFile', ['file_id' => $this->file_id],
            );
            $this->file_info = $response['result'];
        }

        return $this->file_info;
    }

    public function getFileSize(string $units = 'B', int $precision = 5,
    ): int|float {
        $bytes = $this->getFileInfo()['file_size'] ?? 0;

        $division = match ($units) {
            'MB' => 1048576,
            'KB' => 1024,
            default => 1,
        };

        return round($bytes / $division, $precision);
    }

    public function getFilePath(): string
    {
        return $this->api->getApiFileUrl().$this->getFileInfo()['file_path'];
    }

    /**
     * Асинхронно сохраняет файл
     */
    public function save(string $path): string
    {
        if ($this->getFileSize() >= self::MAX_DOWNLOAD_SIZE_BYTES) {
            throw new \RuntimeException('Размер файла превышает 20 МБ');
        }

        $downloadUrl = $this->getFilePath();

        $isDir = str_ends_with($path, DIRECTORY_SEPARATOR)
            || str_ends_with(
                $path, '/',
            );

        if ($isDir
            || is_dir(
                $path,
            )
        ) { // is_dir блокирующий, но быстрый (кэшируется PHP), можно оставить или заменить на File\isDirectory($path)
            $path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $path .= basename($downloadUrl);
        }

        $directory = dirname($path);

        if (!AmpFile\isDirectory($directory)) {
            AmpFile\createDirectoryRecursively($directory, 0775);
        }

        $this->api->downloadFile($downloadUrl, $path);

        return $path;
    }

    public static function getFileId(array $context, ?string $type = null,
    ): ?string {
        $message = $context['result'] ?? $context['message'] ?? [];
        if (empty($message)) {
            return null;
        }

        if ($type !== null) {
            $obj = match ($type) {
                'photo' => end($message['photo']),
                default => $message[$type] ?? null,
            };

            return $obj['file_id'] ?? null;
        }

        $fileTypes = ['photo', 'document', 'video', 'audio', 'voice', 'sticker',
                      'video_note'];
        foreach ($fileTypes as $fileType) {
            if (isset($message[$fileType])) {
                if ($fileType === 'photo') {
                    return end($message['photo'])['file_id'];
                }

                return $message[$fileType]['file_id'];
            }
        }

        return null;
    }
}