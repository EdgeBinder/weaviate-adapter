<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collections;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapter entity extraction and metadata validation.
 */
class WeaviateAdapterEntityExtractionTest extends TestCase
{
    private MockObject|WeaviateClient $mockClient;

    private MockObject|Collections $mockCollections;

    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockCollections = $this->createMock(Collections::class);

        $this->mockClient->method('collections')->willReturn($this->mockCollections);
        $this->mockCollections->method('exists')->willReturn(true);

        $this->adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false],
        ]);
    }

    /**
     * Test extracting entity ID from EntityInterface implementation.
     */
    public function testExtractEntityIdFromEntityInterface(): void
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getId')->willReturn('entity-123');

        $result = $this->adapter->extractEntityId($entity);

        $this->assertEquals('entity-123', $result);
    }

    /**
     * Test extracting entity ID from object with getId method.
     */
    public function testExtractEntityIdFromGetIdMethod(): void
    {
        $entity = new class () {
            public function getId(): string
            {
                return 'custom-entity-456';
            }
        };

        $result = $this->adapter->extractEntityId($entity);

        $this->assertEquals('custom-entity-456', $result);
    }

    /**
     * Test extracting entity ID from object with id property.
     */
    public function testExtractEntityIdFromIdProperty(): void
    {
        $entity = new class () {
            public string $id = 'property-entity-789';
        };

        $result = $this->adapter->extractEntityId($entity);

        $this->assertEquals('property-entity-789', $result);
    }

    /**
     * Test entity ID extraction failure throws EntityExtractionException.
     */
    public function testExtractEntityIdFailure(): void
    {
        $entity = new class () {
            // No ID method or property
        };

        $this->expectException(EntityExtractionException::class);
        $this->expectExceptionMessage('Cannot extract entity ID');

        $this->adapter->extractEntityId($entity);
    }

    /**
     * Test extracting entity type from EntityInterface implementation.
     */
    public function testExtractEntityTypeFromEntityInterface(): void
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getType')->willReturn('Workspace');

        $result = $this->adapter->extractEntityType($entity);

        $this->assertEquals('Workspace', $result);
    }

    /**
     * Test extracting entity type from object with getType method.
     */
    public function testExtractEntityTypeFromGetTypeMethod(): void
    {
        $entity = new class () {
            public function getType(): string
            {
                return 'CustomEntity';
            }
        };

        $result = $this->adapter->extractEntityType($entity);

        $this->assertEquals('CustomEntity', $result);
    }

    /**
     * Test extracting entity type from class name.
     */
    public function testExtractEntityTypeFromClassName(): void
    {
        $entity = new class () {
            // No getType method, should use class name
        };

        $result = $this->adapter->extractEntityType($entity);

        // Should return the short class name (anonymous classes have a specific format)
        $this->assertStringContainsString('WeaviateAdapterEntityExtractionTest.php', $result);
    }

    /**
     * Test metadata validation with valid data.
     */
    public function testValidateAndNormalizeMetadataValid(): void
    {
        $metadata = [
            'access_level' => 'write',
            'confidence_score' => 0.95,
            'tags' => ['production', 'critical'],
            'expires_at' => '2024-12-31T23:59:59Z',
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals($metadata, $result);
    }

    /**
     * Test metadata validation with empty metadata.
     */
    public function testValidateAndNormalizeMetadataEmpty(): void
    {
        $metadata = [];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals([], $result);
    }

    /**
     * Test metadata validation with nested arrays.
     */
    public function testValidateAndNormalizeMetadataWithNestedArrays(): void
    {
        $metadata = [
            'permissions' => [
                'read' => true,
                'write' => false,
                'admin' => false,
            ],
            'user_info' => [
                'id' => 'user-123',
                'name' => 'John Doe',
            ],
        ];

        $result = $this->adapter->validateAndNormalizeMetadata($metadata);

        $this->assertEquals($metadata, $result);
    }

    /**
     * Test metadata validation with invalid data types.
     */
    public function testValidateAndNormalizeMetadataInvalidTypes(): void
    {
        $metadata = [
            'resource' => fopen('php://memory', 'r'), // Invalid resource type
        ];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('Invalid metadata type');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }

    /**
     * Test metadata validation with oversized data.
     */
    public function testValidateAndNormalizeMetadataOversized(): void
    {
        // Create metadata that exceeds size limits
        $largeString = str_repeat('x', 100000); // 100KB string
        $metadata = [
            'large_field' => $largeString,
        ];

        $this->expectException(InvalidMetadataException::class);
        $this->expectExceptionMessage('exceeds limit');

        $this->adapter->validateAndNormalizeMetadata($metadata);
    }
}
