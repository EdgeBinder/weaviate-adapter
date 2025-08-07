<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collections;
use Weaviate\Query\Filter;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapter query conversion methods.
 *
 * These tests focus on the query conversion logic to achieve high coverage.
 */
class WeaviateAdapterQueryConversionTest extends TestCase
{
    /** @var MockObject&WeaviateClient */
    private MockObject $mockClient;

    /** @var MockObject&Collections */
    private MockObject $mockCollections;

    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockCollections = $this->createMock(Collections::class);

        // Set up the mock chain
        $this->mockClient->method('collections')->willReturn($this->mockCollections);
        $this->mockCollections->method('exists')->willReturn(true);

        $this->adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false],
        ]);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with empty query.
     */
    public function testConvertBindingQueryToWeaviateQueryEmpty(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertNull($result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with from entity.
     */
    public function testConvertBindingQueryToWeaviateQueryWithFromEntity(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->from('Workspace', 'workspace-123');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with to entity.
     */
    public function testConvertBindingQueryToWeaviateQueryWithToEntity(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->to('Project', 'project-456');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with binding type.
     */
    public function testConvertBindingQueryToWeaviateQueryWithBindingType(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->type('has_access');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with where conditions.
     */
    public function testConvertBindingQueryToWeaviateQueryWithWhereConditions(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->where('access_level', '=', 'write');
        $query = $query->where('status', '!=', 'inactive');
        $query = $query->where('priority', '>', 5);
        $query = $query->where('score', '<', 100);
        $query = $query->where('name', 'LIKE', '%test%');
        $query = $query->whereNull('deleted_at');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with multiple filters.
     */
    public function testConvertBindingQueryToWeaviateQueryWithMultipleFilters(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->from('Workspace', 'workspace-123');
        $query = $query->to('Project', 'project-456');
        $query = $query->type('has_access');
        $query = $query->where('access_level', '=', 'write');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with single filter returns filter directly.
     */
    public function testConvertBindingQueryToWeaviateQuerySingleFilter(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->type('has_access');

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertInstanceOf(Filter::class, $result);
    }

    /**
     * Test convertBindingQueryToWeaviateQuery with non-BasicWeaviateQueryBuilder.
     */
    public function testConvertBindingQueryToWeaviateQueryWithNonBasicQueryBuilder(): void
    {
        $query = $this->createMock(\EdgeBinder\Contracts\QueryBuilderInterface::class);

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertBindingQueryToWeaviateQuery');
        $method->setAccessible(true);

        $result = $method->invoke($this->adapter, $query);

        $this->assertNull($result);
    }

    /**
     * Test getDefaultConfig returns expected configuration.
     */
    public function testGetDefaultConfig(): void
    {
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('getDefaultConfig');
        $method->setAccessible(true);

        $config = $method->invoke($this->adapter);

        $this->assertIsArray($config);
        $this->assertEquals('EdgeBindings', $config['collection_name']);
        $this->assertTrue($config['schema']['auto_create']);
        $this->assertEquals('text2vec-openai', $config['schema']['vectorizer']);
        $this->assertEquals('openai', $config['vectorizer']['provider']);
        $this->assertEquals('text-embedding-ada-002', $config['vectorizer']['model']);
        $this->assertEquals(100, $config['performance']['batch_size']);
        $this->assertEquals(3600, $config['performance']['vector_cache_ttl']);
    }

    /**
     * Test validateMetadataTypes with valid types.
     */
    public function testValidateMetadataTypesValid(): void
    {
        $metadata = [
            'string' => 'value',
            'int' => 123,
            'float' => 12.34,
            'bool' => true,
            'null' => null,
            'array' => ['nested', 'values'],
            'datetime' => new \DateTimeImmutable(),
        ];

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('validateMetadataTypes');
        $method->setAccessible(true);

        // Should not throw any exception
        $method->invoke($this->adapter, $metadata);
        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * Test validateMetadataTypes with nested arrays.
     */
    public function testValidateMetadataTypesNestedArrays(): void
    {
        $metadata = [
            'config' => [
                'database' => [
                    'host' => 'localhost',
                    'port' => 5432,
                    'options' => [
                        'timeout' => 30,
                        'ssl' => true,
                    ],
                ],
            ],
        ];

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('validateMetadataTypes');
        $method->setAccessible(true);

        // Should not throw any exception
        $method->invoke($this->adapter, $metadata);
        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * Test validateMetadataTypes throws for resource type.
     */
    public function testValidateMetadataTypesThrowsForResource(): void
    {
        $metadata = ['file' => fopen('php://memory', 'r')];

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('validateMetadataTypes');
        $method->setAccessible(true);

        $this->expectException(\EdgeBinder\Exception\InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type \'resource\' at path: file');

        $method->invoke($this->adapter, $metadata);
    }

    /**
     * Test validateMetadataTypes throws for invalid object.
     */
    public function testValidateMetadataTypesThrowsForInvalidObject(): void
    {
        $metadata = ['object' => new \stdClass()];

        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('validateMetadataTypes');
        $method->setAccessible(true);

        $this->expectException(\EdgeBinder\Exception\InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type \'object\' at path: object');

        $method->invoke($this->adapter, $metadata);
    }
}
