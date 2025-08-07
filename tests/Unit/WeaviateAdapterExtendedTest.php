<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collection;
use Weaviate\Collections\Collections;
use Weaviate\Data\DataOperations;
use Weaviate\Query\QueryBuilder;
use Weaviate\WeaviateClient;

/**
 * Extended unit tests for WeaviateAdapter to achieve high coverage.
 *
 * These tests focus on methods and edge cases not covered in the main test file.
 */
class WeaviateAdapterExtendedTest extends TestCase
{
    /** @var MockObject&WeaviateClient */
    private MockObject $mockClient;

    /** @var MockObject&Collections */
    private MockObject $mockCollections;

    /** @var MockObject&Collection */
    private MockObject $mockCollection;

    /** @var MockObject&DataOperations */
    private MockObject $mockData;

    /** @var MockObject&QueryBuilder */
    private MockObject $mockQueryBuilder;

    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockCollections = $this->createMock(Collections::class);
        $this->mockCollection = $this->createMock(Collection::class);
        $this->mockData = $this->createMock(DataOperations::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);

        // Set up the mock chain
        $this->mockClient->method('collections')->willReturn($this->mockCollections);
        $this->mockCollections->method('get')->willReturn($this->mockCollection);
        $this->mockCollection->method('data')->willReturn($this->mockData);
        $this->mockCollection->method('query')->willReturn($this->mockQueryBuilder);

        // Mock collection existence check for schema initialization
        $this->mockCollections->method('exists')->willReturn(true);

        $this->adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false], // Disable auto-create for unit tests
        ]);
    }

    /**
     * Test constructor with auto-create enabled.
     */
    public function testConstructorWithAutoCreateEnabled(): void
    {
        // Create a fresh mock client for this test
        $mockClient = $this->createMock(WeaviateClient::class);
        $mockCollections = $this->createMock(Collections::class);

        $mockClient->method('collections')->willReturn($mockCollections);

        $mockCollections
            ->expects($this->once())
            ->method('exists')
            ->with('TestBindings')
            ->willReturn(false);

        $mockCollections
            ->expects($this->once())
            ->method('create')
            ->with('TestBindings', $this->isType('array'));

        new WeaviateAdapter($mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => true],
        ]);
    }

    /**
     * Test constructor throws SchemaException when collection creation fails.
     */
    public function testConstructorThrowsSchemaExceptionOnCreationFailure(): void
    {
        // Create a fresh mock client for this test
        $mockClient = $this->createMock(WeaviateClient::class);
        $mockCollections = $this->createMock(Collections::class);

        $mockClient->method('collections')->willReturn($mockCollections);

        $mockCollections
            ->method('exists')
            ->willReturn(false);

        $mockCollections
            ->method('create')
            ->willThrowException(new \Exception('Creation failed'));

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Failed to create collection');

        new WeaviateAdapter($mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => true],
        ]);
    }

    /**
     * Test findBetweenEntities without binding type filter.
     */
    public function testFindBetweenEntitiesWithoutBindingType(): void
    {
        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([
            [
                'bindingId' => 'test-binding-123',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-123',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'has_access',
                'metadata' => '{"access_level":"write"}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
        ]);

        $result = $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456'
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(BindingInterface::class, $result[0]);
    }

    /**
     * Test findBetweenEntities with binding type filter.
     */
    public function testFindBetweenEntitiesWithBindingType(): void
    {
        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456',
            'has_access'
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test findBetweenEntities throws exception on error.
     */
    public function testFindBetweenEntitiesThrowsException(): void
    {
        $this->mockCollection
            ->method('query')
            ->willThrowException(new \Exception('Query failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Find between entities failed');

        $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456'
        );
    }

    /**
     * Test query method returns BasicWeaviateQueryBuilder.
     */
    public function testQueryReturnsQueryBuilder(): void
    {
        $queryBuilder = $this->adapter->query();

        $this->assertInstanceOf(QueryBuilderInterface::class, $queryBuilder);
        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $queryBuilder);
    }

    /**
     * Test executeQuery with empty query.
     */
    public function testExecuteQueryWithEmptyQuery(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test executeQuery with limit.
     */
    public function testExecuteQueryWithLimit(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query->limit(5);

        $this->mockQueryBuilder->method('limit')->with(5)->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test executeQuery throws exception on error.
     */
    public function testExecuteQueryThrowsException(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        $this->mockCollection
            ->method('query')
            ->willThrowException(new \Exception('Query execution failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Query execution failed');

        $this->adapter->executeQuery($query);
    }

    /**
     * Test count method.
     */
    public function testCount(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([
            [
                'bindingId' => 'test-1',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-123',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'has_access',
                'metadata' => '{}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
            [
                'bindingId' => 'test-2',
                'fromEntityType' => 'User',
                'fromEntityId' => 'user-789',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'owns',
                'metadata' => '{}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
        ]);

        $result = $this->adapter->count($query);

        $this->assertEquals(2, $result);
    }

    /**
     * Test count throws exception on error.
     */
    public function testCountThrowsException(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        $this->mockCollection
            ->method('query')
            ->willThrowException(new \Exception('Count failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Count query failed');

        $this->adapter->count($query);
    }

    /**
     * Test deleteByEntity throws BadMethodCallException.
     */
    public function testDeleteByEntityThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('deleteByEntity requires Phase 2 client enhancements');

        $this->adapter->deleteByEntity('Workspace', 'workspace-123');
    }

    /**
     * Test UUID generation is deterministic.
     */
    public function testUuidGenerationIsDeterministic(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('generateUuidFromString');
        $method->setAccessible(true);

        $input = 'test-binding-123';
        $uuid1 = $method->invoke($this->adapter, $input);
        $uuid2 = $method->invoke($this->adapter, $input);

        $this->assertEquals($uuid1, $uuid2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1);
    }

    /**
     * Test convertWeaviateResultsToBindings with valid results.
     */
    public function testConvertWeaviateResultsToBindings(): void
    {
        $results = [
            [
                'bindingId' => 'test-binding-1',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-123',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'has_access',
                'metadata' => '{"access_level":"write"}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
            [
                'bindingId' => 'test-binding-2',
                'fromEntityType' => 'User',
                'fromEntityId' => 'user-789',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'owns',
                'metadata' => '{}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertWeaviateResultsToBindings');
        $method->setAccessible(true);

        $bindings = $method->invoke($this->adapter, $results);

        $this->assertCount(2, $bindings);
        $this->assertInstanceOf(BindingInterface::class, $bindings[0]);
        $this->assertInstanceOf(BindingInterface::class, $bindings[1]);
        $this->assertEquals('test-binding-1', $bindings[0]->getId());
        $this->assertEquals('test-binding-2', $bindings[1]->getId());
    }

    /**
     * Test convertWeaviateResultsToBindings handles conversion errors gracefully.
     */
    public function testConvertWeaviateResultsToBindingsHandlesErrors(): void
    {
        $results = [
            [
                'bindingId' => 'test-binding-1',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-123',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'has_access',
                'metadata' => '{"access_level":"write"}',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
            [
                // Invalid result with malformed JSON that will cause conversion error
                'bindingId' => 'test-binding-2',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-123',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-456',
                'bindingType' => 'has_access',
                'metadata' => 'invalid-json{',
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertWeaviateResultsToBindings');
        $method->setAccessible(true);

        $bindings = $method->invoke($this->adapter, $results);

        // Should only return the valid binding, skipping the invalid one
        $this->assertCount(1, $bindings);
        $this->assertInstanceOf(BindingInterface::class, $bindings[0]);
        $this->assertEquals('test-binding-1', $bindings[0]->getId());
    }

    /**
     * Create a test binding for use in tests.
     */
    private function createTestBinding(): BindingInterface
    {
        $now = new \DateTimeImmutable();

        return new Binding(
            id: 'test-binding-123',
            fromType: 'Workspace',
            fromId: 'workspace-456',
            toType: 'Project',
            toId: 'project-789',
            type: 'has_access',
            metadata: ['access_level' => 'write'],
            createdAt: $now,
            updatedAt: $now
        );
    }
}
