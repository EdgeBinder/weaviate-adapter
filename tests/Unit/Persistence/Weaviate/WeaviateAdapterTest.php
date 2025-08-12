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

    /**
     * Test edge cases in anonymous class sanitization for full coverage.
     */
    public function testAnonymousClassSanitizationEdgeCases(): void
    {
        // Test the fallback case when regex doesn't match (line 460)
        // We need to use reflection to test the private method directly
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('sanitizeAnonymousClassName');
        $method->setAccessible(true);

        // Test case 1: String that doesn't match the regex pattern (covers line 460)
        // This should trigger the fallback return statement
        $result = $method->invoke($this->adapter, 'some_random_string');
        $this->assertStringStartsWith('class@anonymous_', $result);
        $this->assertStringContainsString('_', $result); // Should contain hash

        // Test case 2: Another non-matching string to ensure fallback works
        $result = $method->invoke($this->adapter, 'not-anonymous-at-all');
        $this->assertStringStartsWith('class@anonymous_', $result);

        // Test case 3: Empty string (should also trigger fallback)
        $result = $method->invoke($this->adapter, '');
        $this->assertStringStartsWith('class@anonymous_', $result);
    }

    /**
     * Test comprehensive sanitization scenarios to improve coverage.
     */
    public function testAnonymousClassSanitizationComprehensive(): void
    {
        // Use reflection to test the private method directly with various inputs
        $reflection = new \ReflectionClass($this->adapter);
        $method = $reflection->getMethod('sanitizeAnonymousClassName');
        $method->setAccessible(true);

        // Test with complex anonymous class name that has lots of special characters
        $complexName = "class@anonymous\0/path/with/special:chars\$and@symbols/file.php:123\$456";
        $result = $method->invoke($this->adapter, $complexName);

        $this->assertStringContainsString('class@anonymous', $result);
        $this->assertStringNotContainsString("\0", $result);
        $this->assertStringNotContainsString('\\', $result);
        $this->assertStringNotContainsString('/', $result);
        $this->assertStringNotContainsString(':', $result);
        $this->assertStringNotContainsString('$', $result);

        // Test edge case: class@anonymous with minimal path
        $minimalName = "class@anonymous/a";
        $result = $method->invoke($this->adapter, $minimalName);
        $this->assertStringContainsString('class@anonymous_', $result);
        $this->assertStringContainsString('_a', $result);
    }
}
