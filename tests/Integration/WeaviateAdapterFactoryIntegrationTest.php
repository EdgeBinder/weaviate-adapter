<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

/**
 * Integration tests for WeaviateAdapterFactory with AdapterRegistry.
 *
 * These tests verify that the WeaviateAdapterFactory works correctly
 * with the EdgeBinder registry system and can create functional adapters.
 *
 * @group integration
 */
class WeaviateAdapterFactoryIntegrationTest extends TestCase
{
    private WeaviateAdapterFactory $factory;

    private ContainerInterface $container;

    private WeaviateClient $weaviateClient;

    protected function setUp(): void
    {
        // Clear registry for clean test isolation
        AdapterRegistry::clear();

        $this->factory = new WeaviateAdapterFactory();

        // Create mock container
        $this->container = $this->createMock(ContainerInterface::class);

        // Create mock Weaviate client
        $this->weaviateClient = $this->createMock(WeaviateClient::class);

        // Mock collections() method to return a mock that doesn't throw
        $collectionsManager = $this->createMock(\Weaviate\Collections\Collections::class);
        $this->weaviateClient
            ->method('collections')
            ->willReturn($collectionsManager);

        // Setup container to return the Weaviate client
        $this->container
            ->method('has')
            ->willReturnCallback(function (string $service): bool {
                return in_array($service, ['weaviate.client.test', 'weaviate.client.rag']);
            });

        $this->container
            ->method('get')
            ->willReturnCallback(function (string $service) {
                if (in_array($service, ['weaviate.client.test', 'weaviate.client.rag'])) {
                    return $this->weaviateClient;
                }
                throw new \Exception("Service {$service} not found");
            });
    }

    protected function tearDown(): void
    {
        // Clean up registry after each test
        AdapterRegistry::clear();
    }

    /**
     * Test registering and using the WeaviateAdapterFactory with AdapterRegistry.
     */
    public function testAdapterRegistrationAndCreation(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        // Verify registration
        $this->assertTrue(AdapterRegistry::hasAdapter('weaviate'));
        $this->assertContains('weaviate', AdapterRegistry::getRegisteredTypes());

        // Create adapter through registry
        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'weaviate_client' => 'weaviate.client.test',
                'collection_name' => 'TestBindings',
            ],
            'global' => [],
            'container' => $this->container,
        ];

        $adapter = AdapterRegistry::create('weaviate', $config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test creating EdgeBinder instance using fromConfiguration with Weaviate adapter.
     */
    public function testEdgeBinderFromConfiguration(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        // Configuration for EdgeBinder::fromConfiguration
        $config = [
            'adapter' => 'weaviate',
            'weaviate_client' => 'weaviate.client.rag',
            'collection_name' => 'RAGBindings',
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'text2vec-openai',
            ],
        ];

        $globalConfig = [
            'debug' => false,
        ];

        $edgeBinder = EdgeBinder::fromConfiguration($config, $this->container, $globalConfig);

        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
    }

    /**
     * Test that the factory can be retrieved from the registry.
     */
    public function testGetFactoryFromRegistry(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        // Get factory from registry
        $retrievedFactory = AdapterRegistry::getFactory('weaviate');

        $this->assertSame($this->factory, $retrievedFactory);
    }

    /**
     * Test that duplicate registration throws exception.
     */
    public function testDuplicateRegistrationThrowsException(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        // Try to register again
        $this->expectException(\EdgeBinder\Exception\AdapterException::class);
        $this->expectExceptionMessage('Adapter type \'weaviate\' is already registered');

        AdapterRegistry::register($this->factory);
    }

    /**
     * Test that unregistering works correctly.
     */
    public function testUnregisterAdapter(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);
        $this->assertTrue(AdapterRegistry::hasAdapter('weaviate'));

        // Unregister
        $result = AdapterRegistry::unregister('weaviate');
        $this->assertTrue($result);
        $this->assertFalse(AdapterRegistry::hasAdapter('weaviate'));

        // Try to unregister again
        $result = AdapterRegistry::unregister('weaviate');
        $this->assertFalse($result);
    }

    /**
     * Test creating adapter with complex configuration.
     */
    public function testCreateAdapterWithComplexConfiguration(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'weaviate_client' => 'weaviate.client.rag',
                'collection_name' => 'ComplexBindings',
                'schema' => [
                    'auto_create' => true,
                    'vectorizer' => 'text2vec-openai',
                    'properties' => [
                        'custom_field' => ['dataType' => ['text']],
                    ],
                ],
                'vectorizer' => [
                    'provider' => 'openai',
                    'model' => 'text-embedding-ada-002',
                    'api_key' => 'test-key',
                ],
                'performance' => [
                    'batch_size' => 200,
                    'vector_cache_ttl' => 7200,
                    'connection_timeout' => 60,
                ],
            ],
            'global' => [
                'debug' => true,
                'default_metadata_validation' => true,
            ],
            'container' => $this->container,
        ];

        $adapter = AdapterRegistry::create('weaviate', $config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test that registry provides helpful error messages for unknown adapters.
     */
    public function testUnknownAdapterErrorMessage(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        $config = [
            'instance' => ['adapter' => 'unknown'],
            'global' => [],
            'container' => $this->container,
        ];

        $this->expectException(\EdgeBinder\Exception\AdapterException::class);
        $this->expectExceptionMessage('Adapter factory for type \'unknown\' not found. Available types: weaviate');

        AdapterRegistry::create('unknown', $config);
    }

    /**
     * Test clearing the registry.
     */
    public function testClearRegistry(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);
        $this->assertTrue(AdapterRegistry::hasAdapter('weaviate'));

        // Clear registry
        AdapterRegistry::clear();
        $this->assertFalse(AdapterRegistry::hasAdapter('weaviate'));
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
    }

    /**
     * Test that the factory handles container exceptions gracefully.
     */
    public function testContainerExceptionHandling(): void
    {
        // Register the factory
        AdapterRegistry::register($this->factory);

        // Create container that throws exception
        $faultyContainer = $this->createMock(ContainerInterface::class);
        $faultyContainer
            ->method('has')
            ->willReturn(true);
        $faultyContainer
            ->method('get')
            ->willThrowException(new \Exception('Container error'));

        $config = [
            'instance' => [
                'adapter' => 'weaviate',
                'weaviate_client' => 'weaviate.client.test',
            ],
            'global' => [],
            'container' => $faultyContainer,
        ];

        $this->expectException(\EdgeBinder\Exception\AdapterException::class);
        $this->expectExceptionMessage('Failed to create adapter of type \'weaviate\'');

        AdapterRegistry::create('weaviate', $config);
    }
}
