<?php

namespace ZenithGram\ZenithGram\Utils;

use Psr\SimpleCache\CacheInterface;
use Amp\File as AmpFile;

class SimpleFileCache implements CacheInterface
{
    private string $cacheDir;

    public function __construct(string $path = 'storage/cache')
    {
        $this->cacheDir = rtrim($path, '/\\');
        if (!AmpFile\isDirectory($this->cacheDir)) {
            AmpFile\createDirectoryRecursively($this->cacheDir, 0775);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = include $file;
        if (!is_array($content) || $content['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $content['data'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $ttl = is_int($ttl) ? $ttl : 3600;

        $data = [
            'expires' => time() + $ttl,
            'data' => $value
        ];

        $content = "<?php return " . var_export($data, true) . ";";

        return file_put_contents($file, $content, LOCK_EX) !== false;
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
        array_map('unlink', glob($this->cacheDir . '/*.php'));
        return true;
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

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
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
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.php';
    }
}