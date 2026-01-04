<?php

namespace ZenithGram\ZenithGram\Utils;

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ZenithGram\ZenithGram\Dto\ChatDto;
use ZenithGram\ZenithGram\Dto\MessageDto;
use ZenithGram\ZenithGram\Dto\UserDto;
use ZenithGram\ZenithGram\UpdateContext;
use ZenithGram\ZenithGram\ZG;

class DependencyResolver
{
    private array $runtimeCache = [];

    public function __construct(
        private ?ContainerInterface $container = null,
        private ?CacheInterface $cache = null
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * @param callable $handler Обработчик
     * @param ZG $zg Инстанс бота
     * @param array $routeArgs Аргументы из URL/Regex (именованные и нумерованные)
     * @return array Готовый список аргументов для вызова
     */
    public function resolve(callable $handler, ZG $zg, array $routeArgs = []): array
    {
        // 1. Получаем метаданные параметров (из кеша или рефлексии)
        $parametersMeta = $this->getParametersMetadata($handler);

        $dependencies = [];

        // Копия массива для извлечения позиционных аргументов (%s, %n)
        // Фильтруем routeArgs, оставляя только числовые ключи для позиционных параметров
        $positionalArgs = array_filter($routeArgs, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
        // Сбрасываем ключи, чтобы array_shift работал корректно (0, 1, 2...)
        $positionalArgs = array_values($positionalArgs);

        foreach ($parametersMeta as $meta) {
            $name = $meta['name'];
            $typeName = $meta['type'];
            $isBuiltin = $meta['is_builtin'];

            // --- ПРИОРИТЕТ 1: Именованные параметры маршрута ({userId}) ---
            if (array_key_exists($name, $routeArgs)) {
                $dependencies[] = $routeArgs[$name];
                continue;
            }

            // --- ПРИОРИТЕТ 2: Системные объекты библиотеки ---
            if ($typeName) {
                $resolvedSystem = match ($typeName) {
                    ZG::class => $zg,
                    UpdateContext::class => $zg->context,
                    ChatDto::class => $zg->getChat(),
                    UserDto::class => $zg->getUser(),
                    MessageDto::class => $zg->getMessage(),
                    default => null
                };

                if ($resolvedSystem !== null) {
                    $dependencies[] = $resolvedSystem;
                    continue;
                }
            }

            // --- ПРИОРИТЕТ 3: Пользовательский DI Контейнер ---
            // Если пользователь запросил свой класс (например, Database), и он есть в контейнере
            if ($typeName && !$isBuiltin && $this->container && $this->container->has($typeName)) {
                try {
                    $dependencies[] = $this->container->get($typeName);
                    continue;
                } catch (\Throwable $e) {
                    // Если контейнер упал, идем дальше (может это дефолтное значение)
                }
            }

            // --- ПРИОРИТЕТ 4: Позиционные аргументы из Regex (%s, %n) ---
            // Если тип встроенный (string, int) и у нас есть остатки от regex
            if (!empty($positionalArgs)) {
                $dependencies[] = array_shift($positionalArgs);
                continue;
            }

            // --- ПРИОРИТЕТ 5: Дефолтные значения и Nullable ---
            if ($meta['has_default']) {
                $dependencies[] = $meta['default_value'];
                continue;
            }

            if ($meta['allows_null']) {
                $dependencies[] = null;
                continue;
            }

            // Если ничего не нашли -> ошибка
            $handlerName = is_array($handler) ? get_class($handler[0]) . '::' . $handler[1] : 'Closure';
            throw new \RuntimeException("DependencyResolver: Не удалось найти значение для аргумента $$name (тип: $typeName) в $handlerName");
        }

        return $dependencies;
    }

    /**
     * Получает описание параметров. Пытается найти в PSR-16 кеше, иначе парсит.
     */
    private function getParametersMetadata(callable $handler): array
    {
        // Генерируем уникальный ключ для функции/метода
        $key = $this->generateCacheKey($handler);

        // 1. Runtime кеш (самый быстрый, на один запрос)
        if (isset($this->runtimeCache[$key])) {
            return $this->runtimeCache[$key];
        }

        // 2. PSR-16 кеш (между запросами, файлы/redis)
        if ($this->cache && $this->cache->has($key)) {
            $meta = $this->cache->get($key);
            $this->runtimeCache[$key] = $meta;
            return $meta;
        }

        // 3. Рефлексия (медленно)
        $ref = $this->getReflection($handler);
        $params = $ref->getParameters();
        $meta = [];

        foreach ($params as $param) {
            $type = $param->getType();
            $meta[] = [
                'name' => $param->getName(),
                'type' => ($type instanceof ReflectionNamedType) ? $type->getName() : null,
                'is_builtin' => ($type instanceof ReflectionNamedType) && $type->isBuiltin(),
                'allows_null' => $param->allowsNull(),
                'has_default' => $param->isDefaultValueAvailable(),
                'default_value' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        // Сохраняем
        $this->runtimeCache[$key] = $meta;
        if ($this->cache) {
            // TTL можно вынести в конфиг, пока ставим на сутки (86400)
            $this->cache->set($key, $meta, 86400);
        }

        return $meta;
    }

    private function getReflection(callable $handler): ReflectionFunctionAbstract
    {
        if (is_array($handler)) {
            return new ReflectionMethod($handler[0], $handler[1]);
        }
        if (is_object($handler) && !$handler instanceof \Closure) {
            return new ReflectionMethod($handler, '__invoke');
        }
        return new ReflectionFunction($handler);
    }

    private function generateCacheKey(callable $handler): string
    {
        if (is_array($handler)) {
            // Class::method
            return 'zg_refl_' . md5(get_class($handler[0]) . '::' . $handler[1]);
        }
        if (is_string($handler)) {
            // 'function_name'
            return 'zg_refl_' . md5($handler);
        }
        if ($handler instanceof \Closure) {
            // Для замыканий берем файл и строку определения, так как имя у них одинаковое
            $ref = new ReflectionFunction($handler);
            return 'zg_refl_' . md5($ref->getFileName() . '::' . $ref->getStartLine());
        }
        return 'zg_refl_' . md5(serialize($handler));
    }
}