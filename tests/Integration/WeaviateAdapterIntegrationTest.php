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
     * Test finding bindings by entity throws exception (Phase 1 limitation).
     */
    public function testFindByEntity(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('findByEntity requires Phase 2 client enhancements');

        $this->adapter->findByEntity('Workspace', 'workspace-123');
    }

    /**
     * Test finding bindings between entities throws exception (Phase 1 limitation).
     */
    public function testFindBetweenEntities(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('findBetweenEntities requires Phase 2 client enhancements');

        $this->adapter->findBetweenEntities(
            'Workspace',
            'workspace-123',
            'Project',
            'project-456'
        );
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
