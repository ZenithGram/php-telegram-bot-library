<?php
declare(strict_types=1);

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

    public function resolve(callable $handler, ZG $zg, array $routeArgs = []): array
    {
        $parametersMeta = $this->getParametersMetadata($handler);

        $dependencies = [];

        $positionalArgs = array_filter($routeArgs, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
        $positionalArgs = array_values($positionalArgs);

        foreach ($parametersMeta as $meta) {
            $name = $meta['name'];
            $typeName = $meta['type'];
            $isBuiltin = $meta['is_builtin'];

            if (array_key_exists($name, $routeArgs)) {
                $dependencies[] = $routeArgs[$name];
                continue;
            }

            if ($typeName) {
                $resolvedSystem = match ($typeName) {
                    ZG::class => $zg,
                    UpdateContext::class => $zg->getContext(),
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

            if ($typeName && !$isBuiltin && $this->container && $this->container->has($typeName)) {
                try {
                    $dependencies[] = $this->container->get($typeName);
                    continue;
                } catch (\Throwable $e) {
                }
            }

            if (!empty($positionalArgs)) {
                $dependencies[] = array_shift($positionalArgs);
                continue;
            }

            if ($meta['has_default']) {
                $dependencies[] = $meta['default_value'];
                continue;
            }

            if ($meta['allows_null']) {
                $dependencies[] = null;
                continue;
            }

            $handlerName = is_array($handler) ? get_class($handler[0]) . '::' . $handler[1] : 'Closure';
            throw new \RuntimeException("DependencyResolver: Не удалось найти значение для аргумента $$name (тип: $typeName) в $handlerName");
        }

        return $dependencies;
    }

    private function getParametersMetadata(callable $handler): array
    {
        $key = $this->generateCacheKey($handler);

        if (isset($this->runtimeCache[$key])) {
            return $this->runtimeCache[$key];
        }

        if ($this->cache && $this->cache->has($key)) {
            $meta = $this->cache->get($key);
            $this->runtimeCache[$key] = $meta;
            return $meta;
        }

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

        $this->runtimeCache[$key] = $meta;
        if ($this->cache) {
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
            return 'zg_refl_' . md5(get_class($handler[0]) . '::' . $handler[1]);
        }
        if (is_string($handler)) {
            return 'zg_refl_' . md5($handler);
        }
        if ($handler instanceof \Closure) {
            $ref = new ReflectionFunction($handler);
            return 'zg_refl_' . md5($ref->getFileName() . '::' . $ref->getStartLine());
        }
        return 'zg_refl_' . md5(serialize($handler));
    }

    /** @internal */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /** @internal  */
    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }
}