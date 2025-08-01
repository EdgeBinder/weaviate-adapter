<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Exception\AdapterException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapterFactory.
 *
 * @covers \EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory
 */
class WeaviateAdapterFactoryTest extends TestCase
{
    private WeaviateAdapterFactory $factory;

    private ContainerInterface $container;

    private WeaviateClient $weaviateClient;

    protected function setUp(): void
    {
        $this->factory = new WeaviateAdapterFactory();

        // Create mock container
        $this->container = $this->createMock(ContainerInterface::class);

        // Create mock Weaviate client
        $this->weaviateClient = $this->createMock(WeaviateClient::class);
        $this->setupWeaviateClientMock();
    }

    /**
     * Set up the Weaviate client mock to handle collections() calls.
     */
    private function setupWeaviateClientMock(): void
    {
        $collectionsManager = $this->createMock(\Weaviate\Collections\Collections::class);
        $this->weaviateClient
            ->method('collections')
            ->willReturn($collectionsManager);
    }

    /**
     * Test that the factory returns the correct adapter type.
     */
    public function testGetAdapterType(): void
    {
        $this->assertEquals('weaviate', $this->factory->getAdapterType());
    }

    /**
     * Test successful adapter creation with minimal configuration.
     */
    public function testCreateAdapterWithMinimalConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
            ],
            'global' => [],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.test')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.test')
            ->willReturn($this->weaviateClient);

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test successful adapter creation with full configuration.
     */
    public function testCreateAdapterWithFullConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.rag',
                'collection_name' => 'TestBindings',
                'schema' => [
                    'auto_create' => true,
                    'vectorizer' => 'text2vec-openai',
                ],
                'vectorizer' => [
                    'provider' => 'openai',
                    'model' => 'text-embedding-ada-002',
                ],
                'performance' => [
                    'batch_size' => 50,
                    'vector_cache_ttl' => 1800,
                ],
            ],
            'global' => [
                'debug' => true,
            ],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.rag')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.rag')
            ->willReturn($this->weaviateClient);

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test that missing container throws exception.
     */
    public function testCreateAdapterWithMissingContainer(): void
    {
        $config = [
            'instance' => [],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'weaviate\': container');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that missing instance config throws exception.
     */
    public function testCreateAdapterWithMissingInstance(): void
    {
        $config = [
            'container' => $this->container,
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'weaviate\': instance');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that missing global config throws exception.
     */
    public function testCreateAdapterWithMissingGlobal(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'weaviate\': global');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid container type throws exception.
     */
    public function testCreateAdapterWithInvalidContainer(): void
    {
        $config = [
            'container' => 'not-a-container',
            'instance' => [],
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Container must implement Psr\Container\ContainerInterface');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid instance config type throws exception.
     */
    public function testCreateAdapterWithInvalidInstanceConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => 'not-an-array',
            'global' => [],
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Instance configuration must be an array');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid global config type throws exception.
     */
    public function testCreateAdapterWithInvalidGlobalConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [],
            'global' => 'not-an-array',
        ];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Global configuration must be an array');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that missing Weaviate client service throws exception.
     */
    public function testCreateAdapterWithMissingWeaviateClientService(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'missing.service',
            ],
            'global' => [],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('missing.service')
            ->willReturn(false);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Weaviate client service \'missing.service\' not found in container');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid Weaviate client type throws exception.
     */
    public function testCreateAdapterWithInvalidWeaviateClientType(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'invalid.client',
            ],
            'global' => [],
        ];

        $invalidClient = new \stdClass();

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('invalid.client')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('invalid.client')
            ->willReturn($invalidClient);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Service \'invalid.client\' must return a WeaviateClient instance, got stdClass');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid collection name throws exception.
     */
    public function testCreateAdapterWithInvalidCollectionName(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'collection_name' => '', // Empty string
            ],
            'global' => [],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.test')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.test')
            ->willReturn($this->weaviateClient);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Collection name must be a non-empty string');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid schema config throws exception.
     */
    public function testCreateAdapterWithInvalidSchemaConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'schema' => 'not-an-array',
            ],
            'global' => [],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.test')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.test')
            ->willReturn($this->weaviateClient);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Schema configuration must be an array');

        $this->factory->createAdapter($config);
    }
}
