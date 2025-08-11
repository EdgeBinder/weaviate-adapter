<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Persistence\Weaviate;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Registry\AdapterFactoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

/**
 * Tests for WeaviateAdapterFactory.
 */
final class WeaviateAdapterFactoryTest extends TestCase
{
    private WeaviateAdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WeaviateAdapterFactory();
    }

    public function testImplementsAdapterFactoryInterface(): void
    {
        $this->assertInstanceOf(AdapterFactoryInterface::class, $this->factory);
    }

    public function testGetAdapterType(): void
    {
        $this->assertEquals('weaviate', $this->factory->getAdapterType());
    }

    public function testCreateAdapterWithMinimalConfig(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true, // Use the mocked client from container
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithFullConfig(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true, // Use the mocked client from container
                'collection_name' => 'custom_bindings',
                'schema' => [
                    'auto_create' => true,
                    'auto_migrate' => false,
                ],
            ],
            'global' => [
                'debug' => true,
                'some_global_setting' => 'global_value',
            ],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithHttpsScheme(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true, // Use the mocked client from container
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithCustomCollectionName(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true,
                'collection_name' => 'my_custom_bindings',
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterReturnsNewInstanceEachTime(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true,
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter1 = $this->factory->createAdapter($config);
        $adapter2 = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter1);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter2);
        $this->assertNotSame($adapter1, $adapter2);
    }

    public function testCreateAdapterThrowsExceptionForMissingHost(): void
    {
        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'port' => 8080,
            ],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weaviate host is required');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterThrowsExceptionForMissingPort(): void
    {
        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'host' => 'localhost',
            ],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weaviate port is required');

        $this->factory->createAdapter($config);
    }

    public function testCreateAdapterWithDefaultSchemaConfig(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true,
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithDisabledSchemaAutoCreate(): void
    {
        // Create a mock WeaviateClient
        $mockClient = $this->createMock(WeaviateClient::class);

        // Create a mock container that returns the mock client
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true,
                'schema' => [
                    'auto_create' => false,
                ],
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithContainerProvidedClient(): void
    {
        $mockClient = $this->createMock(WeaviateClient::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(WeaviateClient::class)
            ->willReturn(true);
        $container->method('get')
            ->with(WeaviateClient::class)
            ->willReturn($mockClient);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'use_container_client' => true,
            ],
            'global' => [],
            'container' => $container,
        ];

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    public function testCreateAdapterWithInvalidScheme(): void
    {
        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'host' => 'localhost',
                'port' => 8080,
                'scheme' => 'ftp', // Invalid scheme
            ],
            'global' => [],
            'container' => $this->createMock(ContainerInterface::class),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scheme');

        $this->factory->createAdapter($config);
    }
}
