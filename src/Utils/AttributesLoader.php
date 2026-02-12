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
    OnCommand, OnBotCommand, OnStart, OnReferral, OnText, OnTextPreg,
    Btn, OnCallback, OnCallbackPreg, OnInline, OnPhoto, OnVideo,
    OnAudio, OnVoice, OnVideoNote, OnDocument, OnSticker,
    OnNewChatMember, OnLeftChatMember, OnEditedMessage, OnMessage,
    OnDefault, OnState
};

class AttributesLoader
{
    private const ATTRIBUTE_MAP = [
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

    public function __construct(private Bot $bot)
    {
        $this->container = $bot->getContainer();
        $this->cache = $bot->getCache();
    }

    public function registerControllers(array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            $this->processController($controllerClass);
        }
    }

    private function processController(string $className): void
    {
        if (!class_exists($className)) {
            throw new RouteException("Контроллер '$className' не найден.");
        }

        $cacheKey = self::CACHE_KEY_PREFIX . md5($className);
        $routeMap = $this->cache?->get($cacheKey);

        if ($routeMap === null) {
            $routeMap = $this->parseAttributes($className);
            $this->cache?->set($cacheKey, $routeMap, 3600 * 24);
        }

        $instance = $this->resolveController($className);

        foreach ($routeMap as $methodName => $routes) {
            $handler = [$instance, $methodName];
            $routeId = $className . '::' . $methodName;

            foreach ($routes as $route) {
                $botMethod = $route['botMethod'];
                $value = $route['value'];

                $this->bot->$botMethod($routeId, $value)->func($handler);
            }
        }
    }

    private function parseAttributes(string $className): array
    {
        $routeMap = [];
        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            foreach ($method->getAttributes() as $attribute) {
                $attrName = $attribute->getName();

                if (isset(self::ATTRIBUTE_MAP[$attrName])) {
                    $attrInstance = $attribute->newInstance();

                    $routeMap[$methodName][] = [
                        'botMethod' => self::ATTRIBUTE_MAP[$attrName],
                        'value'     => $this->getAttributeValue($attrInstance)
                    ];
                }
            }
        }
        return $routeMap;
    }

    private function resolveController(string $className): object
    {
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