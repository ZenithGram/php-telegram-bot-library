<?php

namespace ZenithGram\ZenithGram\Storage;

use function Amp\File\createDirectoryRecursively;
use function Amp\File\exists;
use function Amp\File\read;
use function Amp\File\write;
use function Amp\File\isDirectory;

class FileStorage implements StorageInterface
{
    private string $storageDir;

    /**
     * Создает экземпляр файлового хранилища
     *
     * @param string $path Путь к папке для хранения сессий
     *
     * @see https://zenithgram.github.io/classes/storage/fileStorage
     */
    public function __construct(string $path = 'storage/sessions')
    {
        $this->storageDir = rtrim($path, '/\\');
    }

    private function getFilePath(int|string $user_id): string
    {
        return $this->storageDir . DIRECTORY_SEPARATOR . $user_id . '.json';
    }

    private function load(int|string $user_id): array
    {
        $file = $this->getFilePath($user_id);

        if (exists($file)) {
            try {
                $content = read($file);
                if (empty($content)) {
                    return [];
                }
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        return [];
    }

    private function save(int|string $user_id, array $data): void
    {
        if (!isDirectory($this->storageDir)) {
            try {
                createDirectoryRecursively($this->storageDir, 0777);
            } catch (\Throwable $e) {
                mkdir($this->storageDir, 0777, true);
            }
        }

        $file = $this->getFilePath($user_id);
        $content = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        write($file, $content);
    }

    /** @internal  */
    public function getState(int|string $user_id): ?string
    {
        $data = $this->load($user_id);
        return $data['state'] ?? null;
    }

    /** @internal  */
    public function setState(int|string $user_id, string $state): void
    {
        $data = $this->load($user_id);
        $data['state'] = $state;
        $this->save($user_id, $data);
    }

    /** @internal  */
    public function clearState(int|string $user_id): void
    {
        $data = $this->load($user_id);
        if (isset($data['state'])) {
            unset($data['state']);
            $this->save($user_id, $data);
        }
    }

    /** @internal  */
    public function getSessionData(int|string $user_id): array
    {
        $data = $this->load($user_id);
        return $data['session'] ?? [];
    }

    /** @internal  */
    public function setSessionData(int|string $user_id, array $data): void
    {
        $currentData = $this->load($user_id);
        $currentData['session'] = array_merge($currentData['session'] ?? [], $data);
        $this->save($user_id, $currentData);
    }

    /** @internal  */
    public function clearSessionData(int|string $user_id): void
    {
        $currentData = $this->load($user_id);
        if (isset($currentData['session'])) {
            unset($currentData['session']);
            $this->save($user_id, $currentData);
        }
    }
}