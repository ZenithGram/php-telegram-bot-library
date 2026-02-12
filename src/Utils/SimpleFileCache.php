<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Utils;

use Psr\SimpleCache\CacheInterface;

use function Amp\File\write;
use function Amp\File\createDirectoryRecursively;
use function Amp\File\deleteFile;
use function Amp\File\exists;
use function Amp\File\isDirectory;
use function Amp\File\listFiles;
use function Amp\File\read;

class SimpleFileCache implements CacheInterface
{
    private string $cacheDir;

    public function __construct(string $path = 'storage/cache')
    {
        $this->cacheDir = rtrim($path, '/\\');
        if (!isDirectory($this->cacheDir)) {
            createDirectoryRecursively($this->cacheDir, 0775);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!exists($file)) {
            return $default;
        }

        try {
            $content = read($file);
            if (empty($content)) {
                return $default;
            }

            $data = unserialize($content, ['allowed_classes' => true]);

            if (!is_array($data) || !isset($data['expires'])) {
                $this->delete($key);

                return $default;
            }

            if ($data['expires'] !== null && $data['expires'] < time()) {
                $this->delete($key);

                return $default;
            }

            return $data['data'];

        } catch (\Throwable) {
            return $default;
        }
    }

    public function set(string $key, mixed $value,
        null|int|\DateInterval $ttl = null,
    ): bool {
        $file = $this->getFilePath($key);
        $ttl = is_int($ttl) ? $ttl : 3600;

        $data = [
            'expires' => time() + $ttl,
            'data'    => $value,
        ];

        try {
            $content = serialize($data);
            write($file, $content);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        try {
            $files = listFiles($this->cacheDir);
            foreach ($files as $file) {
                $fullPath = $this->cacheDir . DIRECTORY_SEPARATOR . $file;
                if (str_ends_with($file, '.cache')) {
                    deleteFile($fullPath);
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }


    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values,
        null|int|\DateInterval $ttl = null,
    ): bool {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}