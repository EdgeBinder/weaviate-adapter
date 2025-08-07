<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Exception\InvalidMetadataException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collection;
use Weaviate\Collections\Collections;
use Weaviate\Data\DataOperations;
use Weaviate\Query\QueryBuilder;
use Weaviate\WeaviateClient;

/**
 * Final coverage tests for WeaviateAdapter to reach close to 100% coverage.
 */
class WeaviateAdapterFinalCoverageTest extends TestCase
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
            'schema' => ['auto_create' => false],
        ]);
    }

    /**
     * Test constructor with custom BindingMapper.
     */
    public function testConstructorWithCustomBindingMapper(): void
    {
        $customMapper = $this->createMock(\EdgeBinder\Adapter\Weaviate\Mapping\BindingMapper::class);

        $adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false],
        ], $customMapper);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test validateAndNormalizeMetadata with JSON serialization failure.
     */
    public function testValidateAndNormalizeMetadataJsonSerializationFailure(): void
    {
        // Create a resource that cannot be JSON encoded
        $resource = fopen('php://memory', 'r');
        $metadata = ['resource' => $resource];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type \'resource\'');

        $this->adapter->validateAndNormalizeMetadata($metadata);

        fclose($resource);
    }

    /**
     * Test extractEntityId with getId method returning non-string.
     */
    public function testExtractEntityIdWithGetIdMethodReturningNonString(): void
    {
        $entity = new class {
            public function getId(): int
            {
                return 123;
            }
        };

        $this->expectException(\EdgeBinder\Exception\EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    /**
     * Test extractEntityId with id property being non-string.
     */
    public function testExtractEntityIdWithIdPropertyNonString(): void
    {
        $entity = new class {
            public int $id = 123;
        };

        $this->expectException(\EdgeBinder\Exception\EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    /**
     * Test extractEntityType with getType method returning non-string.
     */
    public function testExtractEntityTypeWithGetTypeMethodReturningNonString(): void
    {
        $entity = new class {
            public function getType(): int
            {
                return 123;
            }
        };

        $result = $this->adapter->extractEntityType($entity);

        // Should fall back to class name
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test executeQuery with non-BasicWeaviateQueryBuilder.
     */
    public function testExecuteQueryWithNonBasicQueryBuilder(): void
    {
        $query = $this->createMock(\EdgeBinder\Contracts\QueryBuilderInterface::class);

        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test findByEntity throws exception on error.
     */
    public function testFindByEntityThrowsException(): void
    {
        $this->mockCollection
            ->method('query')
            ->willThrowException(new \Exception('Query failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Find by entity failed');

        $this->adapter->findByEntity('Workspace', 'workspace-123');
    }

    /**
     * Test query builder with where conditions using different operators.
     */
    public function testQueryBuilderWithDifferentOperators(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        $query = $query->where('field1', '>', 10);
        $query = $query->where('field2', '<', 100);
        $query = $query->where('field3', '!=', 'value');
        $query = $query->where('field4', 'LIKE', '%pattern%');
        $query = $query->whereNull('field5');

        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test conversion with where conditions that have no value (like EXISTS).
     */
    public function testConvertQueryWithExistsOperator(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        
        // Manually add a condition with EXISTS operator (no value)
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('whereConditions');
        $property->setAccessible(true);
        $property->setValue($query, [
            [
                'field' => 'test_field',
                'operator' => 'EXISTS',
            ],
        ]);

        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test conversion with IS_NULL operator.
     */
    public function testConvertQueryWithIsNullOperator(): void
    {
        $query = new BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');
        
        // Manually add a condition with IS_NULL operator (no value)
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('whereConditions');
        $property->setAccessible(true);
        $property->setValue($query, [
            [
                'field' => 'test_field',
                'operator' => 'IS_NULL',
            ],
        ]);

        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $this->mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test constructor with default configuration.
     */
    public function testConstructorWithDefaultConfiguration(): void
    {
        $adapter = new WeaviateAdapter($this->mockClient);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
    }

    /**
     * Test constructor with merged configuration.
     */
    public function testConstructorWithMergedConfiguration(): void
    {
        $config = [
            'collection_name' => 'CustomBindings',
            'schema' => [
                'auto_create' => false,
                'vectorizer' => 'custom-vectorizer',
            ],
            'performance' => [
                'batch_size' => 50,
            ],
        ];

        $adapter = new WeaviateAdapter($this->mockClient, $config);

        $this->assertInstanceOf(WeaviateAdapter::class, $adapter);
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
