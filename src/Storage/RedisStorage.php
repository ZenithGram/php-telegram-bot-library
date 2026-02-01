<?php

namespace ZenithGram\ZenithGram\Storage;

use Amp\Redis\RedisClient;
use Amp\Redis\RedisConfig;
use RuntimeException;
use ZenithGram\ZenithGram\Exceptions\ZenithGramException;
use function Amp\Redis\createRedisClient;
use ZenithGram\ZenithGram\Interfaces\StorageInterface;

class RedisStorage implements StorageInterface
{
    private RedisClient $redis;
    private string $prefix;

    /**
     * Создает экземпляр асинхронного Redis хранилища (amphp/redis)
     *
     * @param string      $host    Хост Redis
     * @param int         $port    Порт Redis
     * @param string      $prefix  Префикс ключей (по умолчанию 'zg_fsm:')
     * @param int         $dbIndex Индекс базы данных
     * @param string|null $auth    Пароль (если есть)
     *
     * @throws RuntimeException
     * @throws ZenithGramException
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $prefix = 'zg_fsm:',
        int $dbIndex = 0,
        ?string $auth = null
    ) {
        if (!class_exists(RedisClient::class)) {
            throw new ZenithGramException('Для использования RedisStorage необходимо установить пакет "amphp/redis".');
        }

        $this->prefix = $prefix;

        try {
            $uri = "tcp://{$host}:{$port}";

            $config = RedisConfig::fromUri($uri)
                ->withDatabase($dbIndex);

            if ($auth !== null) {
                $config = $config->withPassword($auth);
            }

            $this->redis = createRedisClient($config);
        } catch (\Throwable $e) {
            throw new RuntimeException("Ошибка инициализации RedisStorage: " . $e->getMessage(), 0, $e);
        }
    }

    private function getKey(int|string $userId): string
    {
        return $this->prefix . $userId;
    }

    /** @internal @inheritDoc */
    public function getState(int|string $user_id): ?string
    {
        try {
            $state = $this->redis->execute('HGET', $this->getKey($user_id), 'state');
            return $state === null ? null : (string)$state;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @internal @inheritDoc */
    public function setState(int|string $user_id, string $state): void
    {
        $this->redis->getMap($this->getKey($user_id))->setValue('state', $state);
    }

    /** @internal @inheritDoc */
    public function clearState(int|string $user_id): void
    {
        $this->redis->getMap($this->getKey($user_id))->remove('state');
    }

    /** @internal @inheritDoc */
    public function getSessionData(int|string $user_id): array
    {
        try {
            $data = $this->redis->execute('HGET', $this->getKey($user_id), 'session');

            if ($data === null) {
                return [];
            }

            return json_decode((string)$data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @internal @inheritDoc */
    public function setSessionData(int|string $user_id, array $data): void
    {
        $currentData = $this->getSessionData($user_id);
        $newData = array_merge($currentData, $data);

        $encoded = json_encode($newData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->redis->getMap($this->getKey($user_id))->setValue('session', $encoded);
    }

    /** @internal @inheritDoc */
    public function clearSessionData(int|string $user_id): void
    {
        $this->redis->getMap($this->getKey($user_id))->remove('session');
    }
}