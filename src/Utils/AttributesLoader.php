<?php

namespace ZenithGram\ZenithGram\Utils;

use ReflectionClass;
use ReflectionMethod;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use ZenithGram\ZenithGram\Bot;
use ZenithGram\ZenithGram\Attributes\OnCommand;
// use ZenithGram\ZenithGram\Attributes\OnCallback;
// use ZenithGram\ZenithGram\Attributes\OnState;

class AttributesLoader
{
    private const ATTRIBUTE_MAP = [
        OnCommand::class => 'onCommand',
        //        'ZenithGram\ZenithGram\Attributes\OnCallback' => 'onCallback',
        //        'ZenithGram\ZenithGram\Attributes\OnState'    => 'onState',
        //        'ZenithGram\ZenithGram\Attributes\OnText'     => 'onText',
        //        'ZenithGram\ZenithGram\Attributes\OnBotCommand' => 'onBotCommand',
        // Сюда можно добавлять новые атрибуты по мере расширения библиотеки
    ];

    private const CACHE_KEY_PREFIX = 'zg_attr_map_';

    public function __construct(
        private Bot $bot,
        private ?ContainerInterface $container = null,
        private ?CacheInterface $cache = null
    ) {}

    /**
     * Регистрирует список классов-контроллеров.
     *
     * @param string[] $controllers Массив имен классов (например, [AppController::class])
     */
    public function registerControllers(array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            $this->processController($controllerClass);
        }
    }

    /**
     * Основная логика парсинга контроллера
     */
    private function processController(string $className): void
    {
        // 1. Пытаемся получить карту маршрутов из кеша, чтобы не использовать рефлексию каждый раз
        $cacheKey = self::CACHE_KEY_PREFIX . md5($className);
        $routeMap = $this->cache?->get($cacheKey);

        if ($routeMap === null) {
            $routeMap = $this->parseAttributes($className);
            $this->cache?->set($cacheKey, $routeMap, 3600 * 24); // Кешируем на сутки
        }

        // 2. Инстанцируем контроллер через DI-контейнер или вручную
        $instance = $this->resolveController($className);

        // 3. Регистрируем маршруты в объекте Bot
        foreach ($routeMap as $methodName => $attributes) {
            $handler = [$instance, $methodName];
            $routeId = $className . '::' . $methodName;

            foreach ($attributes as $attrData) {
                $this->registerRoute(
                    $routeId,
                    $attrData['botMethod'],
                    $attrData['value'],
                    $handler
                );
            }
        }
    }

    /**
     * Рефлексия: собирает данные об атрибутах методов
     */
    private function parseAttributes(string $className): array
    {
        $map = [];
        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            foreach ($method->getAttributes() as $attribute) {
                $attrClassName = $attribute->getName();

                // Проверяем, есть ли этот атрибут в нашем списке поддерживаемых
                if (isset(self::ATTRIBUTE_MAP[$attrClassName])) {
                    $attrInstance = $attribute->newInstance();

                    // Извлекаем значение (например, текст команды или стейта)
                    // Предполагается, что у всех атрибутов первое свойство — это значение/команда
                    $value = $this->getAttributeValue($attrInstance);

                    $map[$methodName][] = [
                        'botMethod' => self::ATTRIBUTE_MAP[$attrClassName],
                        'value'     => $value
                    ];
                }
            }
        }
        return $map;
    }

    /**
     * Создает объект контроллера, используя Dependency Injection
     */
    private function resolveController(string $className): object
    {
        if ($this->container && $this->container->has($className)) {
            return $this->container->get($className);
        }

        return new $className();
    }

    /**
     * Вызывает соответствующий метод у Bot (onCommand, onState и т.д.)
     */
    private function registerRoute(string $id, string $botMethod, mixed $value, callable $handler): void
    {
        // Вызов типа: $this->bot->onCommand('id', '/start')->func($handler)
        $action = $this->bot->$botMethod($id, $value);
        $action->func($handler);
    }

    /**
     * Хелпер для получения основного значения атрибута
     */
    private function getAttributeValue(object $attrInstance): mixed
    {
        $vars = get_object_vars($attrInstance);
        return reset($vars); // Возвращает первое свойство объекта
    }
}