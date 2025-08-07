<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collection;
use Weaviate\Collections\Collections;
use Weaviate\Data\DataOperations;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapter CRUD operations.
 *
 * These tests use mocked Weaviate client to test the adapter logic
 * without requiring a real Weaviate instance.
 */
class WeaviateAdapterTest extends TestCase
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
            'schema' => ['auto_create' => false], // Disable auto-create for unit tests
        ]);
    }

    /**
     * Test successful binding storage.
     */
    public function testStoreBindingSuccess(): void
    {
        $binding = $this->createTestBinding();

        $this->mockData
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function ($properties) {
                    return $properties['id'] === '73478489-9745-4b2c-9a1c-a7ef6418e04a' // UUID from binding ID
                        && $properties['bindingId'] === 'test-binding-123'
                        && $properties['fromEntityType'] === 'Workspace'
                        && $properties['fromEntityId'] === 'workspace-456'
                        && $properties['toEntityType'] === 'Project'
                        && $properties['toEntityId'] === 'project-789'
                        && $properties['bindingType'] === 'has_access'
                        && $properties['metadata'] === '{"access_level":"write"}'; // JSON string
                })
            )
            ->willReturn(['id' => 'test-binding-123']);

        $this->adapter->store($binding);
    }

    /**
     * Test storage failure throws WeaviateException.
     */
    public function testStoreBindingFailure(): void
    {
        $binding = $this->createTestBinding();

        $this->mockData
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Weaviate server error'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Storage operation failed');

        $this->adapter->store($binding);
    }

    /**
     * Test successful binding retrieval.
     */
    public function testFindBindingSuccess(): void
    {
        $bindingId = 'test-binding-123';
        $weaviateUuid = '73478489-9745-4b2c-9a1c-a7ef6418e04a';
        $weaviateData = [
            'id' => $weaviateUuid,
            'properties' => [
                'bindingId' => $bindingId,
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-456',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-789',
                'bindingType' => 'has_access',
                'metadata' => '{"access_level":"write"}', // JSON string
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-01-01T00:00:00Z',
            ],
        ];

        $this->mockData
            ->expects($this->once())
            ->method('get')
            ->with($weaviateUuid) // Expect UUID, not original binding ID
            ->willReturn($weaviateData);

        $result = $this->adapter->find($bindingId);

        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals($bindingId, $result->getId());
        $this->assertEquals('Workspace', $result->getFromType());
        $this->assertEquals('workspace-456', $result->getFromId());
        $this->assertEquals('Project', $result->getToType());
        $this->assertEquals('project-789', $result->getToId());
        $this->assertEquals('has_access', $result->getType());
        $this->assertEquals(['access_level' => 'write'], $result->getMetadata());
    }

    /**
     * Test finding non-existent binding returns null.
     */
    public function testFindBindingNotFound(): void
    {
        $bindingId = 'non-existent-binding';
        $weaviateUuid = 'c66b3d18-1d52-4477-a6a9-85577763141d'; // UUID for 'non-existent-binding'

        $this->mockData
            ->expects($this->once())
            ->method('get')
            ->with($weaviateUuid) // Expect UUID
            ->willThrowException(new \Exception('404 Not Found'));

        $result = $this->adapter->find($bindingId);

        $this->assertNull($result);
    }

    /**
     * Test successful binding deletion.
     */
    public function testDeleteBindingSuccess(): void
    {
        $bindingId = 'test-binding-123';
        $weaviateUuid = '73478489-9745-4b2c-9a1c-a7ef6418e04a';

        $this->mockData
            ->expects($this->once())
            ->method('delete')
            ->with($weaviateUuid) // Expect UUID
            ->willReturn(true);

        $this->adapter->delete($bindingId);
    }

    /**
     * Test deletion failure throws WeaviateException.
     */
    public function testDeleteBindingFailure(): void
    {
        $bindingId = 'test-binding-123';
        $weaviateUuid = '73478489-9745-4b2c-9a1c-a7ef6418e04a';

        $this->mockData
            ->expects($this->once())
            ->method('delete')
            ->with($weaviateUuid) // Expect UUID
            ->willReturn(false);

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage("Failed to delete binding: {$bindingId}");

        $this->adapter->delete($bindingId);
    }

    /**
     * Test successful metadata update.
     */
    public function testUpdateMetadataSuccess(): void
    {
        $bindingId = 'test-binding-123';
        $weaviateUuid = '73478489-9745-4b2c-9a1c-a7ef6418e04a';
        $newMetadata = ['access_level' => 'read', 'updated_by' => 'admin'];

        $this->mockData
            ->expects($this->once())
            ->method('update')
            ->with(
                $weaviateUuid, // Expect UUID
                $this->callback(function ($updateData) {
                    return $updateData['metadata'] === '{"access_level":"read","updated_by":"admin"}'
                        && isset($updateData['updatedAt']);
                })
            )
            ->willReturn(['id' => $weaviateUuid, 'properties' => ['metadata' => json_encode($newMetadata)]]);

        $this->adapter->updateMetadata($bindingId, $newMetadata);
    }

    /**
     * Test metadata update failure throws WeaviateException.
     */
    public function testUpdateMetadataFailure(): void
    {
        $bindingId = 'test-binding-123';
        $newMetadata = ['access_level' => 'read'];

        $this->mockData
            ->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception('Update failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Metadata update failed');

        $this->adapter->updateMetadata($bindingId, $newMetadata);
    }

    /**
     * Test finding bindings by entity works with v0.5.0 API.
     */
    public function testFindByEntityWorksWithV050Api(): void
    {
        // Mock the query chain for findByEntity
        $mockQueryBuilder = $this->createMock(\Weaviate\Query\QueryBuilder::class);
        $mockQueryBuilder->method('where')->willReturnSelf();
        $mockQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockQueryBuilder->method('fetchObjects')->willReturn([
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

        $this->mockCollection->method('query')->willReturn($mockQueryBuilder);

        $result = $this->adapter->findByEntity('Workspace', 'workspace-123');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(BindingInterface::class, $result[0]);
        $this->assertEquals('test-binding-123', $result[0]->getId());
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
