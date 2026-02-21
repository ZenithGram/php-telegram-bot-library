<?php

declare(strict_types=1);

namespace ZenithGram\ZenithGram\Utils;

use ReflectionClass;
use ReflectionMethod;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use ZenithGram\ZenithGram\Bot;
use ZenithGram\ZenithGram\Exceptions\RouteException;
use ZenithGram\ZenithGram\Attributes\{
    OnCommand,
    OnBotCommand,
    OnStart,
    OnReferral,
    OnText,
    OnTextPreg,
    Btn,
    OnCallback,
    OnCallbackPreg,
    OnInline,
    OnPhoto,
    OnVideo,
    OnAudio,
    OnVoice,
    OnVideoNote,
    OnDocument,
    OnSticker,
    OnNewChatMember,
    OnLeftChatMember,
    OnEditedMessage,
    OnMessage,
    OnDefault,
    OnState
};

class AttributesLoader
{
    private const ATTRIBUTE_MAP
        = [
            OnStart::class          => 'onStart',
            OnBotCommand::class     => 'onBotCommand',
            OnCommand::class        => 'onCommand',
            OnReferral::class       => 'onReferral',
            OnText::class           => 'onText',
            OnTextPreg::class       => 'onTextPreg',
            Btn::class              => 'btn',
            OnCallback::class       => 'onCallback',
            OnCallbackPreg::class   => 'onCallbackPreg',
            OnInline::class         => 'onInline',
            OnPhoto::class          => 'onPhoto',
            OnVideo::class          => 'onVideo',
            OnAudio::class          => 'onAudio',
            OnVoice::class          => 'onVoice',
            OnVideoNote::class      => 'onVideoNote',
            OnDocument::class       => 'onDocument',
            OnSticker::class        => 'onSticker',
            OnNewChatMember::class  => 'onNewChatMember',
            OnLeftChatMember::class => 'onLeftChatMember',
            OnEditedMessage::class  => 'onEditedMessage',
            OnMessage::class        => 'onMessage',
            OnDefault::class        => 'onDefault',
            OnState::class          => 'onState',
        ];

    private const CACHE_KEY_PREFIX = 'zg_attr_map_v1_';

    private ?CacheInterface $cache;
    private ?ContainerInterface $container;
    private ?\Closure $factory = null;

    public function __construct(private Bot $bot)
    {
        $this->container = $bot->getContainer();
        $this->cache = $bot->getCache();
    }

    /**
     * Устанавливает кастомную фабрику для создания контроллеров (Lightweight
     * DI)
     *
     * @param callable $factory Функция, принимающая FQCN (строку) и
     *                          возвращающая объект или null
     *
     * @see https://zenithgram.github.io/classes/botMethods/attributes#setFactory
     */
    public function setFactory(callable $factory): self
    {
        $this->factory = \Closure::fromCallable($factory);

        return $this;
    }

    /**
     * Автоматически сканирует директорию, находит все классы и регистрирует
     * их.
     *
     * @param string $directory     Абсолютный путь к папке (например, __DIR__
     *                              . '/Controllers')
     * @param string $rootNamespace Базовый namespace этой папки (например,
     *                              'App\Controllers')
     *
     * @return void
     * @throws RouteException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @see https://zenithgram.github.io/classes/botMethods/attributes#scanDirectory
     */
    public function scanDirectory(string $directory, string $rootNamespace,
    ): void {
        $cacheKey = self::CACHE_KEY_PREFIX.'dir_'.md5($directory);

        $classes = $this->cache?->get($cacheKey);

        if ($classes === null) {
            $classes = $this->findClassesInDirectory(
                $directory, $rootNamespace,
            );

            if (!empty($classes)) {
                $this->cache?->set($cacheKey, $classes, 3600 * 24);
            }
        }

        $this->registerControllers($classes);
    }

    /**
     * Регистрирует массив переданных классов-контроллеров
     *
     * @param array $controllers Массив строк (FQCN классов)
     *
     * @return void
     * @throws RouteException
     * @see https://zenithgram.github.io/classes/botMethods/attributes#registerControllers
     */
    public function registerControllers(array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            $this->processController($controllerClass);
        }
    }

    private function findClassesInDirectory(string $directory,
        string $rootNamespace,
    ): array {
        $classes = [];
        $realDirectory = realpath($directory);

        if (!$realDirectory || !is_dir($realDirectory)) {
            throw new RouteException(
                "Директория для сканирования контроллеров '$directory' не найдена.",
            );
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realDirectory),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace(
                    $realDirectory, '', $file->getPathname(),
                );
                $relativePath = ltrim($relativePath, '/\\');

                $classPath = str_replace(['/', '\\', '.php'], ['\\', '\\', ''],
                    $relativePath);

                $className = rtrim($rootNamespace, '\\').'\\'.$classPath;

                if (class_exists($className)) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }

    private function processController(string $className): void
    {
        if (!class_exists($className)) {
            throw new RouteException("Контроллер '$className' не найден.");
        }

        $cacheKey = self::CACHE_KEY_PREFIX.md5($className);
        $routeMap = $this->cache?->get($cacheKey);

        if ($routeMap === null) {
            $routeMap = $this->parseAttributes($className);
            if (!empty($routeMap)) {
                $this->cache?->set($cacheKey, $routeMap, 3600 * 24);
            }
        }

        if (empty($routeMap)) {
            return;
        }

        $instance = $this->resolveController($className);

        foreach ($routeMap as $methodName => $routes) {
            $handler = [$instance, $methodName];
            $routeId = $className.'::'.$methodName;

            foreach ($routes as $route) {
                $botMethod = $route['botMethod'];
                $args = $route['args'];

                if ($botMethod === 'btn') {
                    $id = $args['id'] ?? null;
                    $text = $args['text'] ?? null;

                    if ($id === null) {
                        $id = reset($args);
                    }

                    $this->bot->btn($id, $text)->func($handler);
                } else {
                    $mainValue = reset($args);
                    $this->bot->$botMethod($routeId, $mainValue)->func(
                        $handler,
                    );
                }
            }
        }
    }

    private function parseAttributes(string $className): array
    {
        $routeMap = [];
        $reflection = new ReflectionClass($className);

        foreach (
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method
        ) {
            $methodName = $method->getName();

            foreach ($method->getAttributes() as $attribute) {
                $attrName = $attribute->getName();

                if (isset(self::ATTRIBUTE_MAP[$attrName])) {
                    $attrInstance = $attribute->newInstance();
                    $routeMap[$methodName][] = [
                        'botMethod' => self::ATTRIBUTE_MAP[$attrName],
                        'value'     => $this->getAttributeValue($attrInstance),
                    ];
                }
            }
        }

        return $routeMap;
    }

    private function resolveController(string $className): object
    {
        if ($this->factory !== null) {
            $instance = ($this->factory)($className);
            if ($instance instanceof $className) {
                return $instance;
            }
        }

        if ($this->container && $this->container->has($className)) {
            return $this->container->get($className);
        }

        return new $className();
    }

    private function getAttributeValue(object $attrInstance): mixed
    {
        $vars = get_object_vars($attrInstance);

        return reset($vars);
    }
}