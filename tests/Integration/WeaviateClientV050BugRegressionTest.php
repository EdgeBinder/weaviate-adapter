<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration;

use EdgeBinder\Adapter\Weaviate\Mapping\BindingMapper;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Binding;
use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;

/**
 * Integration test to verify the Weaviate PHP client v0.5.0 bug is properly handled.
 *
 * This test reproduces the exact issue described in EDGEBINDER_WEAVIATE_ADAPTER_BUG_REPORT.md:
 * - Weaviate PHP client v0.5.0 changed query behavior to require explicit property selection
 * - Queries without returnProperties() return empty objects with only _additional.id
 * - This caused EdgeBinder to fail with TypeError when creating Binding objects
 *
 * The test verifies:
 * 1. The bug exists at the raw Weaviate client level
 * 2. Our WeaviateAdapter correctly handles this by always including returnProperties()
 * 3. BindingMapper properly processes complete data
 * 4. Regression protection to ensure this issue doesn't reoccur
 */
class WeaviateClientV050BugRegressionTest extends TestCase
{
    private WeaviateClient $client;
    private string $testCollectionName;
    private WeaviateAdapter $adapter;
    private BindingMapper $bindingMapper;

    protected function setUp(): void
    {
        // Skip if no Weaviate instance is available
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate instance not available for integration testing');
        }

        $this->client = WeaviateClient::connectToLocal();
        $this->testCollectionName = 'TestBindings_V050Bug_' . uniqid();

        $this->adapter = new WeaviateAdapter($this->client, [
            'collection_name' => $this->testCollectionName,
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'none', // Disable vectorizer for testing
            ],
        ]);

        $this->bindingMapper = new BindingMapper(new MetadataMapper());
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
     * Test that reproduces the exact bug described in the bug report.
     *
     * This test demonstrates:
     * 1. Data storage works correctly
     * 2. Raw Weaviate client queries without returnProperties() return empty objects (the bug)
     * 3. Raw Weaviate client queries with returnProperties() return complete objects
     * 4. WeaviateAdapter queries return complete objects (our fix works)
     * 5. BindingMapper fails with incomplete data but works with complete data
     */
    public function testWeaviateClientV050BugReproductionAndFix(): void
    {
        // Create and store a test binding
        $binding = $this->createTestBinding();
        $this->adapter->store($binding);

        $collection = $this->client->collections()->get($this->testCollectionName);

        // === PART 1: Demonstrate the bug exists at raw client level ===

        // Query without returnProperties() - this should return empty objects (the bug)
        $emptyResults = $collection->query()->fetchObjects();

        $this->assertNotEmpty($emptyResults, 'Should find stored objects');
        $this->assertCount(1, $emptyResults, 'Should find exactly one object');

        $emptyObject = $emptyResults[0];

        // Verify the bug: object should only have _additional.id, no properties
        $this->assertArrayHasKey('_additional', $emptyObject, 'Should have _additional field');
        $this->assertArrayHasKey('id', $emptyObject['_additional'], 'Should have _additional.id');

        // The bug: all binding properties should be missing
        $this->assertArrayNotHasKey('bindingId', $emptyObject, 'Bug: bindingId should be missing');
        $this->assertArrayNotHasKey('fromEntityType', $emptyObject, 'Bug: fromEntityType should be missing');
        $this->assertArrayNotHasKey('fromEntityId', $emptyObject, 'Bug: fromEntityId should be missing');
        $this->assertArrayNotHasKey('toEntityType', $emptyObject, 'Bug: toEntityType should be missing');
        $this->assertArrayNotHasKey('toEntityId', $emptyObject, 'Bug: toEntityId should be missing');
        $this->assertArrayNotHasKey('bindingType', $emptyObject, 'Bug: bindingType should be missing');
        $this->assertArrayNotHasKey('metadata', $emptyObject, 'Bug: metadata should be missing');

        // Document the bug reproduction for debugging
        // Query without returnProperties() result: {"_additional": {"id": "..."}} only

        // === PART 2: Demonstrate the fix works at raw client level ===

        // Query with explicit returnProperties() - this should return complete objects
        $completeResults = $collection->query()
            ->returnProperties(['bindingId', 'fromEntityType', 'fromEntityId', 'toEntityType', 'toEntityId', 'bindingType', 'metadata', 'createdAt', 'updatedAt'])
            ->fetchObjects();

        $this->assertNotEmpty($completeResults, 'Should find stored objects');
        $this->assertCount(1, $completeResults, 'Should find exactly one object');

        $completeObject = $completeResults[0];

        // Verify the fix: object should have all required properties
        $this->assertArrayHasKey('bindingId', $completeObject, 'Fix: bindingId should be present');
        $this->assertArrayHasKey('fromEntityType', $completeObject, 'Fix: fromEntityType should be present');
        $this->assertArrayHasKey('fromEntityId', $completeObject, 'Fix: fromEntityId should be present');
        $this->assertArrayHasKey('toEntityType', $completeObject, 'Fix: toEntityType should be present');
        $this->assertArrayHasKey('toEntityId', $completeObject, 'Fix: toEntityId should be present');
        $this->assertArrayHasKey('bindingType', $completeObject, 'Fix: bindingType should be present');
        $this->assertArrayHasKey('metadata', $completeObject, 'Fix: metadata should be present');

        // Verify the data is correct
        $this->assertEquals($binding->getId(), $completeObject['bindingId']);
        $this->assertEquals($binding->getFromType(), $completeObject['fromEntityType']);
        $this->assertEquals($binding->getFromId(), $completeObject['fromEntityId']);
        $this->assertEquals($binding->getToType(), $completeObject['toEntityType']);
        $this->assertEquals($binding->getToId(), $completeObject['toEntityId']);
        $this->assertEquals($binding->getType(), $completeObject['bindingType']);

        // Document the fix verification for debugging
        // Query with returnProperties() returns complete object with all fields

        // === PART 3: Demonstrate BindingMapper behavior ===

        // BindingMapper should fail with empty object (reproducing the original error)
        try {
            $this->bindingMapper->fromWeaviateObject($emptyObject);
            $this->fail('BindingMapper should fail with empty object data');
        } catch (\TypeError $e) {
            $this->assertStringContainsString('Argument #1 ($id) must be of type string, null given', $e->getMessage());
        }

        // BindingMapper should work with complete object
        $reconstructedBinding = $this->bindingMapper->fromWeaviateObject($completeObject);
        $this->assertEquals($binding->getId(), $reconstructedBinding->getId());
        $this->assertEquals($binding->getFromType(), $reconstructedBinding->getFromType());
        $this->assertEquals($binding->getFromId(), $reconstructedBinding->getFromId());

        // === PART 4: Verify WeaviateAdapter fix works ===

        // Our adapter should return complete objects because it always uses returnProperties()
        $adapterResults = $this->adapter->findByEntity('Workspace', 'workspace-123');

        $this->assertNotEmpty($adapterResults, 'Adapter should find stored bindings');
        $this->assertCount(1, $adapterResults, 'Adapter should find exactly one binding');

        $adapterBinding = $adapterResults[0];
        $this->assertEquals($binding->getId(), $adapterBinding->getId());
        $this->assertEquals($binding->getFromType(), $adapterBinding->getFromType());
        $this->assertEquals($binding->getFromId(), $adapterBinding->getFromId());
    }

    /**
     * Test that all WeaviateAdapter query methods include returnProperties().
     *
     * This is a regression test to ensure the fix remains in place.
     */
    public function testAllQueryMethodsIncludeReturnProperties(): void
    {
        // Create and store test data
        $binding = $this->createTestBinding();
        $this->adapter->store($binding);

        // Test findByEntity
        $results = $this->adapter->findByEntity('Workspace', 'workspace-123');
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(\EdgeBinder\Contracts\BindingInterface::class, $results[0]);

        // Test findBetweenEntities
        $results = $this->adapter->findBetweenEntities('Workspace', 'workspace-123', 'Project', 'project-456');
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(\EdgeBinder\Contracts\BindingInterface::class, $results[0]);

        // Test executeQuery via query builder
        $results = $this->adapter->executeQuery(
            $this->adapter->query()
                ->from('Workspace', 'workspace-123')
                ->type('has_access')
        );
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(\EdgeBinder\Contracts\BindingInterface::class, $results[0]);

        // Regression test passed: All WeaviateAdapter query methods return complete Binding objects
    }

    private function createTestBinding(): Binding
    {
        return new Binding(
            id: 'test-binding-' . uniqid(),
            fromType: 'Workspace',
            fromId: 'workspace-123',
            toType: 'Project',
            toId: 'project-456',
            type: 'has_access',
            metadata: ['access_level' => 'write', 'role' => 'admin'],
            createdAt: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2024-01-01T00:00:00+00:00')
        );
    }

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
}
