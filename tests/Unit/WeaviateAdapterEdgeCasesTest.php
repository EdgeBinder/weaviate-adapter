<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Exception\InvalidMetadataException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collection;
use Weaviate\Collections\Collections;
use Weaviate\Data\DataOperations;
use Weaviate\WeaviateClient;

/**
 * Edge case tests for WeaviateAdapter to reach maximum coverage.
 */
class WeaviateAdapterEdgeCasesTest extends TestCase
{
    /** @var MockObject&WeaviateClient */
    private MockObject $mockClient;

    /** @var MockObject&Collections */
    private MockObject $mockCollections;

    /** @var MockObject&Collection */
    private MockObject $mockCollection;

    /** @var MockObject&DataOperations */
    private MockObject $mockData;

    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockCollections = $this->createMock(Collections::class);
        $this->mockCollection = $this->createMock(Collection::class);
        $this->mockData = $this->createMock(DataOperations::class);

        // Set up the mock chain
        $this->mockClient->method('collections')->willReturn($this->mockCollections);
        $this->mockCollections->method('get')->willReturn($this->mockCollection);
        $this->mockCollection->method('data')->willReturn($this->mockData);

        // Mock collection existence check for schema initialization
        $this->mockCollections->method('exists')->willReturn(true);

        $this->adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false],
        ]);
    }

    /**
     * Test validateAndNormalizeMetadata with metadata that cannot be JSON encoded.
     */
    public function testValidateAndNormalizeMetadataWithNonJsonEncodableData(): void
    {
        // Create a circular reference that cannot be JSON encoded
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $metadata = ['circular' => $obj1];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type \'object\'');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    /**
     * Test validateAndNormalizeMetadata with valid DateTime object.
     */
    public function testValidateAndNormalizeMetadataWithDateTime(): void
    {
        $metadata = [
            'created_at' => new \DateTimeImmutable('2024-01-01T00:00:00Z'),
            'updated_at' => new \DateTime('2024-01-01T00:00:00Z'),
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals($metadata, $result);
    }

    /**
     * Test extractEntityId with empty string from getId method.
     */
    public function testExtractEntityIdWithEmptyStringFromGetId(): void
    {
        $entity = new class () {
            public function getId(): string
            {
                return '';
            }
        };

        $this->expectException(\EdgeBinder\Exception\EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    /**
     * Test extractEntityId with empty string from id property.
     */
    public function testExtractEntityIdWithEmptyStringFromIdProperty(): void
    {
        $entity = new class () {
            public string $id = '';
        };

        $this->expectException(\EdgeBinder\Exception\EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    /**
     * Test extractEntityType with empty string from getType method.
     */
    public function testExtractEntityTypeWithEmptyStringFromGetType(): void
    {
        $entity = new class () {
            public function getType(): string
            {
                return '';
            }
        };

        $result = $this->adapter->extractEntityType($entity);

        // Should fall back to class name
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test validateMetadataTypes with deeply nested arrays.
     */
    public function testValidateMetadataTypesWithDeeplyNestedArrays(): void
    {
        $metadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'value' => 'deep_value',
                            'number' => 42,
                            'boolean' => true,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals($metadata, $result);
    }

    /**
     * Test validateMetadataTypes with resource in deeply nested array.
     */
    public function testValidateMetadataTypesWithResourceInDeeplyNestedArray(): void
    {
        $metadata = [
            'config' => [
                'database' => [
                    'connection' => [
                        'handle' => fopen('php://memory', 'r'),
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type \'resource\' at path: config.database.connection.handle');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    /**
     * Test validateAndNormalizeMetadata with exactly at size limit.
     */
    public function testValidateAndNormalizeMetadataAtSizeLimit(): void
    {
        // Create metadata that is just under the 64KB limit
        $largeString = str_repeat('x', 65500); // Just under limit
        $metadata = ['data' => $largeString];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals($metadata, $result);
    }

    /**
     * Test conversion with unknown operator.
     */
    public function testConvertQueryWithUnknownOperator(): void
    {
        $query = new \EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        // Manually add a condition with unknown operator
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('whereConditions');
        $property->setAccessible(true);
        $property->setValue($query, [
            [
                'field' => 'test_field',
                'operator' => 'UNKNOWN_OPERATOR',
                'value' => 'test_value',
            ],
        ]);

        $this->mockCollection->method('query')->willReturn($this->createMock(\Weaviate\Query\QueryBuilder::class));
        $mockQueryBuilder = $this->mockCollection->query();
        $mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test conversion with condition that has neither field nor property.
     */
    public function testConvertQueryWithConditionMissingField(): void
    {
        $query = new \EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        // Manually add a condition without field or property
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('whereConditions');
        $property->setAccessible(true);
        $property->setValue($query, [
            [
                'operator' => '=',
                'value' => 'test_value',
            ],
        ]);

        $this->mockCollection->method('query')->willReturn($this->createMock(\Weaviate\Query\QueryBuilder::class));
        $mockQueryBuilder = $this->mockCollection->query();
        $mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }

    /**
     * Test conversion with condition that has field but no value and no special operator.
     */
    public function testConvertQueryWithConditionMissingValue(): void
    {
        $query = new \EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder($this->mockClient, 'TestBindings');

        // Manually add a condition with field but no value
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('whereConditions');
        $property->setAccessible(true);
        $property->setValue($query, [
            [
                'field' => 'test_field',
                'operator' => '=',
                // No value
            ],
        ]);

        $this->mockCollection->method('query')->willReturn($this->createMock(\Weaviate\Query\QueryBuilder::class));
        $mockQueryBuilder = $this->mockCollection->query();
        $mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockQueryBuilder->method('fetchObjects')->willReturn([]);

        $result = $this->adapter->executeQuery($query);

        $this->assertIsArray($result);
    }
}
