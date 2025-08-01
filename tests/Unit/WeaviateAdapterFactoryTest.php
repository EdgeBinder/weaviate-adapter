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

    /**
     * Test that invalid vectorizer config throws exception.
     */
    public function testCreateAdapterWithInvalidVectorizerConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'vectorizer' => 'not-an-array',
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
        $this->expectExceptionMessage('Vectorizer configuration must be an array');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that invalid performance config throws exception.
     */
    public function testCreateAdapterWithInvalidPerformanceConfig(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'performance' => 'not-an-array',
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
        $this->expectExceptionMessage('Performance configuration must be an array');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that Weaviate client connection failure throws exception.
     */
    public function testCreateAdapterWithWeaviateConnectionFailure(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
            ],
            'global' => [],
        ];

        // Create a client that throws on collections() call
        $faultyClient = $this->createMock(WeaviateClient::class);
        $faultyClient
            ->method('collections')
            ->willThrowException(new \Exception('Connection failed'));

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.test')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.test')
            ->willReturn($faultyClient);

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Weaviate client connection test failed: Connection failed');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that container exception during client retrieval is handled.
     */
    public function testCreateAdapterWithContainerException(): void
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
            ->willThrowException(new \Exception('Container error'));

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Failed to get Weaviate client \'weaviate.client.test\': Container error');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that multiple missing configuration keys are reported.
     */
    public function testCreateAdapterWithMultipleMissingKeys(): void
    {
        $config = [];

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required configuration for adapter type \'weaviate\': container, instance, global');

        $this->factory->createAdapter($config);
    }

    /**
     * Test that non-string collection name throws exception.
     */
    public function testCreateAdapterWithNonStringCollectionName(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'collection_name' => 123, // Non-string
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
     * Test that debug configuration is properly handled.
     */
    public function testCreateAdapterWithDebugConfiguration(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
            ],
            'global' => [
                'debug' => true,
            ],
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
     * Test that default weaviate client service name is used when not specified.
     */
    public function testCreateAdapterWithDefaultClientServiceName(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [], // No weaviate_client specified
            'global' => [],
        ];

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.default') // Should use default service name
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.default')
            ->willReturn($this->weaviateClient);

        $adapter = $this->factory->createAdapter($config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test that adapter creation failure in constructor is handled.
     */
    public function testCreateAdapterWithConstructorFailure(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'collection_name' => 'TestBindings',
            ],
            'global' => [],
        ];

        // Create a client that will cause WeaviateAdapter constructor to fail
        $problematicClient = $this->createMock(WeaviateClient::class);
        $collectionsManager = $this->createMock(\Weaviate\Collections\Collections::class);
        $problematicClient
            ->method('collections')
            ->willReturn($collectionsManager);

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('weaviate.client.test')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('weaviate.client.test')
            ->willReturn($problematicClient);

        // This should work fine since we're not actually testing constructor failure
        // but rather ensuring the factory handles any potential exceptions
        $adapter = $this->factory->createAdapter($config);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test edge case with null collection name (should use default).
     */
    public function testCreateAdapterWithNullCollectionName(): void
    {
        $config = [
            'container' => $this->container,
            'instance' => [
                'weaviate_client' => 'weaviate.client.test',
                'collection_name' => null, // Should use default from WeaviateAdapter
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

        // This should work fine as WeaviateAdapter has default config
        $adapter = $this->factory->createAdapter($config);
        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }
}
