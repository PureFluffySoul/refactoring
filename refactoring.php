<?php

/*
Задание - https://gist.github.com/shurunov/1191a9bfec81d09be7b445ac7845bd3e

1. Отсутствует типизация параметров и возвращаемых значений у методов класса.
2. Отсутствует интерфейс провайдера данных. Его следует выделить, чтобы исключить зависимость от конкретной реализации
3. Необоснованное применение наследования в DecoratorManager. Наследование влечет за собой сильную зависимость
DecoratorManager'а от DataProvider'а. Его следует заменить на композицию, с использованием выделенного ранее интерфейса.
4. Необоснованное расширение интерфейса DataProvider за счет добавления DecoratorManager::getResponse(). Во-первых,
название не отражает, чем новый метод отличается от уже существовавшего метода DataProvider::get(), без чтения
реализации не разобраться. Кроме того, такой подход влечет за собой рост числа специализированных методов
без возможности комбинировать отдельные расширенные возможности.
Лучше использовать шаблон Decorator, в результате мы можем комбинировать необходимое поведение и не надо изменять код,
использующий базовую функцию.
5. Из описания задачи не следует, что ошибки надо обрабатывать (кроме логирования). В предоставленной реализации,
клиентский код получит данные в виде пустого массива, нет возможности узнать об ошибке и обработать ее. Кроме того,
логируется только факт возникновения ошибки, без подробностей.
6. Не стоит использовать глобальное пространство имен - это приведет к конфликтам имен в будущем.
7. Необоснованные public методы и свойства.
8. Использование массива в качестве параметра и типа возвращаемого значения в DataProvider::get() - некрасиво, неудобно
и может привести к проблемам, поскольку отсутствует типизация данных. Стоит завести классы для запроса и ответа для
конкретизации их значений.
9. Название метода DataProvider::getCacheKey() не совсем верно отражает происходящее внутри, но возможно это вкусовщина.
*/

namespace natalia;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use JsonSerializable;
use DateTime;
use Exception;

class DataProviderResponse
{
    // some implementation
}

class DataProviderRequest implements JsonSerializable
{
    // some implementation

    public function jsonSerialize()
    {
        // some implementation
    }
}

interface DataProviderInterface
{
    public function get(DataProviderRequest $request): DataProviderResponse;
}

class DataProvider implements DataProviderInterface
{
    public function __construct(
        private string $host,
        private string $user,
        private string $password
    ) {}

    public function get(DataProviderRequest $request): DataProviderResponse
    {
        // some implementation
    }
}

class CacheDecorator implements DataProviderInterface
{
    public function __construct(private CacheItemPoolInterface $cache, private DataProviderInterface $provider) {}

    public function get(DataProviderRequest $request): DataProviderResponse
    {
        $cacheKey = $this->makeCacheKey($request);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $response = $this->provider->get($request);

        $expiration = new DateTime('+1 day');
        $cacheItem
            ->set($response)
            ->expiresAt($expiration);

         return $response;
    }

    private function makeCacheKey(DataProviderRequest $request): string
    {
        return json_encode($request);
    }
}

class LoggerDecorator implements DataProviderInterface
{
    public function __construct(private LoggerInterface $logger, private DataProviderInterface $provider) {}

    public function get(DataProviderRequest $request): DataProviderResponse
    {
        try {
            return $this->provider->get($request);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());

            throw $e;
        }
    }
}
