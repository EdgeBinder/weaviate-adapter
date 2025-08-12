<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Persistence\Weaviate;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Contracts\EntityInterface;
use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapter entity extraction methods.
 */
final class WeaviateAdapterTest extends TestCase
{
    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock WeaviateClient for unit testing
        $mockClient = $this->createMock(WeaviateClient::class);

        $this->adapter = new WeaviateAdapter($mockClient, [
            'collection_name' => 'TestCollection',
            'schema' => ['auto_create' => false], // Disable auto-create for unit tests
        ]);
    }

    public function testExtractEntityTypeWithEntityInterface(): void
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getType')->willReturn('CustomType');

        $result = $this->adapter->extractEntityType($entity);

        $this->assertSame('CustomType', $result);
    }

    public function testExtractEntityTypeWithGetTypeMethod(): void
    {
        $entity = new class () {
            public function getType(): string
            {
                return 'MyCustomType';
            }
        };

        $result = $this->adapter->extractEntityType($entity);

        $this->assertSame('MyCustomType', $result);
    }

    public function testExtractEntityTypeWithGetTypeReturningNonString(): void
    {
        $entity = new class () {
            public function getType(): int
            {
                return 123;
            }
        };

        $result = $this->adapter->extractEntityType($entity);

        // Should fall back to sanitized class name
        $this->assertStringContainsString('class@anonymous', $result);
        $this->assertStringNotContainsString("\0", $result); // No null bytes
        $this->assertIsString($result);
    }

    public function testExtractEntityTypeWithAnonymousClass(): void
    {
        $entity = new class () {
            // Anonymous class without getType method
        };

        $result = $this->adapter->extractEntityType($entity);

        // Should return sanitized anonymous class name
        $this->assertStringContainsString('class@anonymous', $result);
        $this->assertStringNotContainsString("\0", $result); // No null bytes
        $this->assertStringNotContainsString('\\', $result); // No backslashes
        $this->assertStringNotContainsString('/', $result); // No forward slashes
        $this->assertStringNotContainsString(':', $result); // No colons
        $this->assertStringNotContainsString('$', $result); // No dollar signs
        $this->assertIsString($result);
    }

    public function testExtractEntityTypeWithRegularClass(): void
    {
        $entity = new \stdClass();

        $result = $this->adapter->extractEntityType($entity);

        $this->assertSame('stdClass', $result);
    }

    public function testExtractEntityIdWithEntityInterface(): void
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getId')->willReturn('entity-123');

        $result = $this->adapter->extractEntityId($entity);

        $this->assertSame('entity-123', $result);
    }

    public function testExtractEntityIdWithGetIdMethod(): void
    {
        $entity = new class () {
            public function getId(): string
            {
                return 'custom-id-456';
            }
        };

        $result = $this->adapter->extractEntityId($entity);

        $this->assertSame('custom-id-456', $result);
    }

    public function testExtractEntityIdFallsBackToObjectHash(): void
    {
        $entity = new class () {
            // No getId method or id property
        };

        $result = $this->adapter->extractEntityId($entity);

        $this->assertStringStartsWith('obj_', $result);
        $this->assertIsString($result);
    }

    /**
     * Test that anonymous class sanitization produces consistent results.
     */
    public function testAnonymousClassSanitizationConsistency(): void
    {
        // Create two instances of the same anonymous class structure
        $createEntity = function () {
            return new class () {
                public function test(): string
                {
                    return 'test';
                }
            };
        };

        $entity1 = $createEntity();
        $entity2 = $createEntity();

        $type1 = $this->adapter->extractEntityType($entity1);
        $type2 = $this->adapter->extractEntityType($entity2);

        // Different instances should have different types (different line numbers)
        // but both should be properly sanitized
        $this->assertStringContainsString('class@anonymous', $type1);
        $this->assertStringContainsString('class@anonymous', $type2);
        $this->assertStringNotContainsString("\0", $type1);
        $this->assertStringNotContainsString("\0", $type2);
    }
}
