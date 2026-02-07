<?php

namespace Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ZenithGram\ZenithGram\Utils\DependencyResolver;
use ZenithGram\ZenithGram\ZG;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(DependencyResolver::class)]
class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        // 👇 Временный перехватчик ошибок для отладки
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            fwrite(STDERR, "\n🛑 NOTICE: $errstr\n   in $errfile:$errline\n");
            return false; // Позволить PHPUnit тоже обработать это
        }, E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED);

        $this->resolver = new DependencyResolver();
    }


    /**
     * Тест 1: Резолвинг стандартных классов (ZG)
     */
    public function testResolveSystemClasses(): void
    {
        $zgMock = $this->createMock(ZG::class);

        $handler = function (ZG $bot) {};

        $args = $this->resolver->resolve($handler, $zgMock);

        $this->assertCount(1, $args);
        $this->assertSame($zgMock, $args[0]);
    }

    /**
     * Тест 2: Резолвинг аргументов маршрута (именованные параметры)
     * Пример: /ban {user_id}
     */
    public function testResolveRouteArguments(): void
    {
        $zgMock = $this->createMock(ZG::class);

        $handler = function (int $userId, string $reason) {};

        $routeArgs = ['userId' => 555, 'reason' => 'spam'];

        $resolved = $this->resolver->resolve($handler, $zgMock, $routeArgs);

        $this->assertEquals(555, $resolved[0]);
        $this->assertEquals('spam', $resolved[1]);
    }

    /**
     * Тест 3: Смешанный резолвинг (ZG + RouteArgs + Default)
     */
    public function testResolveMixedArgs(): void
    {
        $zgMock = $this->createMock(ZG::class);

        $handler = function (ZG $bot, int $id, bool $isAdmin = false) {};

        $routeArgs = ['id' => 100];

        $resolved = $this->resolver->resolve($handler, $zgMock, $routeArgs);

        $this->assertSame($zgMock, $resolved[0]);
        $this->assertEquals(100, $resolved[1]);
        $this->assertFalse($resolved[2]);
    }

    /**
     * Тест 4: Резолвинг из PSR-11 Контейнера
     */
    public function testResolveFromContainer(): void
    {
        $zgMock = $this->createMock(ZG::class);
        $containerMock = $this->createMock(ContainerInterface::class);
        $serviceMock = new \stdClass();

        $containerMock->method('has')->with(\stdClass::class)->willReturn(true);
        $containerMock->method('get')->with(\stdClass::class)->willReturn($serviceMock);

        $this->resolver->setContainer($containerMock);

        $handler = function (\stdClass $service) {};

        $resolved = $this->resolver->resolve($handler, $zgMock);

        $this->assertSame($serviceMock, $resolved[0]);
    }

    /**
     * Тест 5: Работа кеша метаданных
     * Проверяем, что Reflection вызывается только один раз
     */
    public function testCachingReflection(): void
    {
        $cacheMock = $this->createMock(CacheInterface::class);
        $zgMock = $this->createMock(ZG::class);

        $cachedMeta = [
            [
                'name' => 'fakeArg',
                'type' => null,
                'is_builtin' => true,
                'allows_null' => true,
                'has_default' => false,
                'default_value' => null
            ]
        ];

        $cacheMock->expects($this->atLeastOnce())
            ->method('has')
            ->willReturn(true);

        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturn($cachedMeta);

        $this->resolver->setCache($cacheMock);

        $handler = function () {};

        $routeArgs = ['fakeArg' => 999];

        $args = $this->resolver->resolve($handler, $zgMock, $routeArgs);

        $this->assertCount(1, $args);
        $this->assertEquals(999, $args[0]);
    }

    /**
     * Тест 5 (исправленный): Проверка записи в кеш при холодном старте
     */
    public function testWritesToCache(): void
    {
        $cacheMock = $this->createMock(CacheInterface::class);
        $zgMock = $this->createMock(ZG::class);

        $cacheMock->expects($this->once())->method('set');

        $this->resolver->setCache($cacheMock);

        $handler = function($a) {};
        $this->resolver->resolve($handler, $zgMock, ['a' => 1]);
    }

    /**
     * Тест 6: Ошибка, если аргумент не найден
     */
    public function testExceptionOnMissingArg(): void
    {
        $zgMock = $this->createMock(ZG::class);
        $handler = function (int $missing) {};

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DependencyResolver: Не удалось найти значение');

        $this->resolver->resolve($handler, $zgMock, []);
    }
}