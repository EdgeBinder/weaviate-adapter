<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;

/**
 * Integration tests for WeaviateAdapter with real Weaviate instance.
 *
 * These tests require a running Weaviate instance and test the full
 * integration between the adapter and Weaviate.
 *
 * @group integration
 */
class WeaviateAdapterIntegrationTest extends TestCase
{
    private WeaviateClient $client;

    private WeaviateAdapter $adapter;

    private string $testCollectionName;

    protected function setUp(): void
    {
        // Skip if no Weaviate instance is available
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate instance not available for integration testing');
        }

        $this->client = WeaviateClient::connectToLocal();
        $this->testCollectionName = 'TestBindings_' . uniqid();

        $this->adapter = new WeaviateAdapter($this->client, [
            'collection_name' => $this->testCollectionName,
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'none', // Disable vectorizer for testing
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->client) && isset($this->testCollectionName)) {
            try {
                // Clean up test collection
                $this->client->collections()->delete($this->testCollectionName);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Test complete CRUD workflow with real Weaviate instance.
     */
    public function testCrudWorkflow(): void
    {
        // Create a test binding
        $binding = $this->createTestBinding();

        // Test store
        $this->adapter->store($binding);

        // Test find
        $foundBinding = $this->adapter->find($binding->getId());
        $this->assertNotNull($foundBinding);
        $this->assertEquals($binding->getId(), $foundBinding->getId());
        $this->assertEquals($binding->getFromType(), $foundBinding->getFromType());
        $this->assertEquals($binding->getFromId(), $foundBinding->getFromId());
        $this->assertEquals($binding->getToType(), $foundBinding->getToType());
        $this->assertEquals($binding->getToId(), $foundBinding->getToId());
        $this->assertEquals($binding->getType(), $foundBinding->getType());
        $this->assertEquals($binding->getMetadata(), $foundBinding->getMetadata());

        // Test update metadata
        $newMetadata = [
            'access_level' => 'read',
            'updated_by' => 'admin',
            'updated_reason' => 'security_review',
        ];
        $this->adapter->updateMetadata($binding->getId(), $newMetadata);

        // Verify metadata update
        $updatedBinding = $this->adapter->find($binding->getId());
        $this->assertNotNull($updatedBinding);
        $this->assertEquals($newMetadata, $updatedBinding->getMetadata());

        // Test delete
        $this->adapter->delete($binding->getId());

        // Verify deletion
        $deletedBinding = $this->adapter->find($binding->getId());
        $this->assertNull($deletedBinding);
    }

    /**
     * Test finding bindings by entity works with v0.5.0 API.
     */
    public function testFindByEntity(): void
    {
        // Create a test binding first
        $binding = $this->createTestBinding();
        $this->adapter->store($binding);

        // Find bindings by the 'from' entity
        $results = $this->adapter->findByEntity('Workspace', 'workspace-123');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($binding->getId(), $results[0]->getId());

        // Find bindings by the 'to' entity
        $results = $this->adapter->findByEntity('Project', 'project-456');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($binding->getId(), $results[0]->getId());

        // Find bindings by non-existent entity
        $results = $this->adapter->findByEntity('NonExistent', 'non-existent-123');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test finding bindings between entities works with v0.5.0 API.
     */
    public function testFindBetweenEntities(): void
    {
        // Create a test binding first
        $binding = $this->createTestBinding();
        $this->adapter->store($binding);

        // Find bindings between the specific entities
        $results = $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456'
        );

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($binding->getId(), $results[0]->getId());

        // Find bindings between non-existent entities
        $results = $this->adapter->findBetweenEntities(
            'NonExistent',
            'non-existent-123',
            'AlsoNonExistent',
            'also-non-existent-456'
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results);

        // Find bindings with specific binding type
        $results = $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456',
            'has_access'
        );

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($binding->getId(), $results[0]->getId());

        // Find bindings with wrong binding type
        $results = $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456',
            'wrong_type'
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test count method works with v0.5.0 API.
     */
    public function testCount(): void
    {
        // Create multiple test bindings
        $binding1 = $this->createTestBinding('binding-1', 'workspace-123', 'project-456', 'has_access');
        $binding2 = $this->createTestBinding('binding-2', 'workspace-123', 'project-789', 'has_access');
        $binding3 = $this->createTestBinding('binding-3', 'workspace-456', 'project-123', 'has_member');

        $this->adapter->store($binding1);
        $this->adapter->store($binding2);
        $this->adapter->store($binding3);

        // Test count with query builder
        $queryBuilder = $this->adapter->query()
            ->from('Workspace', 'workspace-123')
            ->type('has_access');

        $count = $this->adapter->count($queryBuilder);

        $this->assertIsInt($count);
        $this->assertEquals(2, $count); // Should find 2 bindings for workspace-123 with has_access type

        // Test count with different criteria
        $queryBuilder2 = $this->adapter->query()
            ->type('has_member');

        $count2 = $this->adapter->count($queryBuilder2);

        $this->assertIsInt($count2);
        $this->assertEquals(1, $count2); // Should find 1 binding with has_member type

        // Test count with no matches
        $queryBuilder3 = $this->adapter->query()
            ->from('NonExistent', 'non-existent-123');

        $count3 = $this->adapter->count($queryBuilder3);

        $this->assertIsInt($count3);
        $this->assertEquals(0, $count3); // Should find 0 bindings for non-existent entity
    }

    /**
     * Test deleting bindings by entity throws exception (Phase 1 limitation).
     */
    public function testDeleteByEntity(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('deleteByEntity requires Phase 2 client enhancements');

        $this->adapter->deleteByEntity('Workspace', 'workspace-123');
    }

    /**
     * Test entity extraction with various entity types.
     */
    public function testEntityExtraction(): void
    {
        // Test with EntityInterface implementation
        $entityWithInterface = new class () implements \EdgeBinder\Contracts\EntityInterface {
            public function getId(): string
            {
                return 'interface-entity-123';
            }

            public function getType(): string
            {
                return 'InterfaceEntity';
            }
        };

        $this->assertEquals('interface-entity-123', $this->adapter->extractEntityId($entityWithInterface));
        $this->assertEquals('InterfaceEntity', $this->adapter->extractEntityType($entityWithInterface));

        // Test with custom object
        $customEntity = new class () {
            public function getId(): string
            {
                return 'custom-entity-456';
            }

            public function getType(): string
            {
                return 'CustomEntity';
            }
        };

        $this->assertEquals('custom-entity-456', $this->adapter->extractEntityId($customEntity));
        $this->assertEquals('CustomEntity', $this->adapter->extractEntityType($customEntity));
    }

    /**
     * Check if Weaviate instance is available for testing.
     */
    private function isWeaviateAvailable(): bool
    {
        try {
            $client = WeaviateClient::connectToLocal();
            // Try to access collections to test if Weaviate is responding
            $client->collections();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a test binding for integration tests.
     */
    private function createTestBinding(
        ?string $id = null,
        string $fromId = 'workspace-123',
        string $toId = 'project-456',
        string $type = 'has_access'
    ): BindingInterface {
        $now = new \DateTimeImmutable();

        return new Binding(
            id: $id ?? 'test-binding-' . uniqid(),
            fromType: 'Workspace',
            fromId: $fromId,
            toType: 'Project',
            toId: $toId,
            type: $type,
            metadata: [
                'access_level' => 'write',
                'granted_by' => 'admin',
                'confidence_score' => 0.95,
            ],
            createdAt: $now,
            updatedAt: $now
        );
    }
}
