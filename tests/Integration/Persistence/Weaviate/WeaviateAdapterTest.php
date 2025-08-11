<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration\Persistence\Weaviate;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Testing\AbstractAdapterTestSuite;
use Weaviate\WeaviateClient;

/**
 * Integration tests for WeaviateAdapter using the standard AbstractAdapterTestSuite.
 *
 * This test class extends AbstractAdapterTestSuite to ensure that WeaviateAdapter
 * passes all the comprehensive integration tests that validate adapter compliance
 * with the EdgeBinder v0.6.0 specification.
 */
final class WeaviateAdapterTest extends AbstractAdapterTestSuite
{
    private static ?WeaviateClient $client = null;

    private static string $collectionName = 'EdgeBinderTestBindings';

    protected function createAdapter(): PersistenceAdapterInterface
    {
        if (self::$client === null) {
            self::$client = WeaviateClient::connectToLocal();
        }

        $config = [
            'collection_name' => self::$collectionName,
            'schema' => [
                'auto_create' => true,
                'auto_migrate' => true,
            ],
        ];

        return new WeaviateAdapter(self::$client, $config);
    }

    protected function cleanupAdapter(): void
    {
        if (self::$client === null) {
            return;
        }

        try {
            // Delete the test collection to clean up
            $collections = self::$client->collections();
            if ($collections->exists(self::$collectionName)) {
                $collections->delete(self::$collectionName);
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors - they shouldn't fail the tests
            error_log('Cleanup warning: ' . $e->getMessage());
        }
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Skip tests if Weaviate is not available
        if (!self::isWeaviateAvailable()) {
            self::markTestSkipped('Weaviate is not available for testing');
        }
    }

    private static function isWeaviateAvailable(): bool
    {
        try {
            $client = WeaviateClient::connectToLocal();

            // Try to connect to Weaviate by accessing collections
            $client->collections();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========================================
    // Weaviate-Specific Tests for 100% Coverage
    // ========================================

    /**
     * Test additional Weaviate-specific scenarios for better coverage.
     */
    public function testAdditionalWeaviateScenarios(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Test binding with various metadata types that Weaviate can handle
        $binding = $this->edgeBinder->bind($user, $project, 'has_access', [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => ['nested' => 'value'],
        ]);

        $found = $this->adapter->find($binding->getId());
        $this->assertNotNull($found);
        $this->assertEquals('value', $found->getMetadata()['string']);
        $this->assertEquals(42, $found->getMetadata()['int']);
        $this->assertEquals(3.14, $found->getMetadata()['float']);
        $this->assertTrue($found->getMetadata()['bool']);
        $this->assertNull($found->getMetadata()['null']);
        $this->assertEquals(['nested' => 'value'], $found->getMetadata()['array']);
    }

    /**
     * Test Weaviate-specific UUID generation and handling.
     */
    public function testWeaviateUuidHandling(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Create multiple bindings to test UUID consistency
        $binding1 = $this->edgeBinder->bind($user, $project, 'has_access', ['level' => 1]);
        $binding2 = $this->edgeBinder->bind($user, $project, 'has_access', ['level' => 2]);

        // Verify that each binding has a unique ID
        $this->assertNotEquals($binding1->getId(), $binding2->getId());

        // Verify that we can find both bindings
        $found1 = $this->adapter->find($binding1->getId());
        $found2 = $this->adapter->find($binding2->getId());

        $this->assertNotNull($found1);
        $this->assertNotNull($found2);
        $this->assertEquals(1, $found1->getMetadata()['level']);
        $this->assertEquals(2, $found2->getMetadata()['level']);
    }

    /**
     * Test Weaviate-specific error handling and recovery.
     */
    public function testWeaviateErrorHandling(): void
    {
        // Test finding a non-existent binding
        $result = $this->adapter->find('non-existent-id');
        $this->assertNull($result);

        // Test deleting a non-existent binding
        $this->expectException(\EdgeBinder\Exception\BindingNotFoundException::class);
        $this->adapter->delete('non-existent-id');
    }

    /**
     * Test Weaviate-specific metadata serialization and deserialization.
     */
    public function testWeaviateMetadataSerialization(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Test with complex nested metadata
        $complexMetadata = [
            'permissions' => [
                'read' => true,
                'write' => false,
                'admin' => false,
            ],
            'tags' => ['important', 'project-alpha'],
            'score' => 85.5,
            'created_by' => 'system',
        ];

        $binding = $this->edgeBinder->bind($user, $project, 'has_access', $complexMetadata);
        $found = $this->adapter->find($binding->getId());

        $this->assertNotNull($found);
        $this->assertEquals($complexMetadata, $found->getMetadata());
    }

    /**
     * Test Weaviate collection management.
     */
    public function testWeaviateCollectionManagement(): void
    {
        // This test verifies that the adapter properly manages the Weaviate collection
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        // Create a binding to ensure the collection exists
        $binding = $this->edgeBinder->bind($user, $project, 'has_access', ['test' => true]);

        // Verify the collection exists by checking if we can query it
        $results = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(1, $results);
        $this->assertEquals($binding->getId(), $results[0]->getId());
    }

    /**
     * Test Weaviate-specific query performance with larger datasets.
     */
    public function testWeaviateQueryPerformance(): void
    {
        $user = $this->createTestEntity('user-1', 'User');

        // Create multiple bindings for performance testing
        $bindings = [];
        for ($i = 1; $i <= 10; ++$i) {
            $project = $this->createTestEntity("project-{$i}", 'Project');
            $bindings[] = $this->edgeBinder->bind($user, $project, 'has_access', [
                'level' => $i,
                'priority' => $i % 3,
            ]);
        }

        // Test that queries still work efficiently with multiple bindings
        $results = $this->adapter->findByEntity('User', 'user-1');
        $this->assertCount(10, $results);

        // Test filtering works correctly
        $query = $this->edgeBinder->query()
            ->from($user)
            ->where('metadata.priority', '=', 1);

        $filteredResults = $query->get();
        $this->assertGreaterThan(0, $filteredResults->count());

        // Verify all results have the correct priority
        foreach ($filteredResults->getBindings() as $binding) {
            $this->assertEquals(1, $binding->getMetadata()['priority']);
        }
    }

    /**
     * Test Weaviate-specific date handling.
     */
    public function testWeaviateDateHandling(): void
    {
        $user = $this->createTestEntity('user-1', 'User');
        $project = $this->createTestEntity('project-1', 'Project');

        $binding = $this->edgeBinder->bind($user, $project, 'has_access', []);

        // Test that created and updated timestamps are properly handled
        $found = $this->adapter->find($binding->getId());
        $this->assertNotNull($found);
        $this->assertInstanceOf(\DateTimeInterface::class, $found->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $found->getUpdatedAt());

        // Test querying by timestamp (this tests the date field handling)
        $timestamp = $found->getCreatedAt()->getTimestamp();
        $query = $this->edgeBinder->query()
            ->where('createdAt', '=', $timestamp);

        $results = $query->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }
}
