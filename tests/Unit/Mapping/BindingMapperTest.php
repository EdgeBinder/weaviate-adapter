<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Mapping;

use EdgeBinder\Adapter\Weaviate\Mapping\BindingMapper;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BindingMapper.
 */
class BindingMapperTest extends TestCase
{
    /** @var MockObject&MetadataMapper */
    private MockObject $mockMetadataMapper;

    private BindingMapper $bindingMapper;

    protected function setUp(): void
    {
        $this->mockMetadataMapper = $this->createMock(MetadataMapper::class);
        $this->bindingMapper = new BindingMapper($this->mockMetadataMapper);
    }

    /**
     * Test converting BindingInterface to Weaviate properties.
     */
    public function testToWeaviateProperties(): void
    {
        $binding = $this->createTestBinding();

        // Mock metadata mapper
        $this->mockMetadataMapper
            ->expects($this->once())
            ->method('serialize')
            ->with(['access_level' => 'write', 'granted_by' => 'admin'])
            ->willReturn('{"access_level":"write","granted_by":"admin"}');

        $result = $this->bindingMapper->toWeaviateProperties($binding);

        $this->assertIsArray($result);
        $this->assertEquals('test-binding-123', $result['bindingId']);
        $this->assertEquals('Workspace', $result['fromEntityType']);
        $this->assertEquals('workspace-456', $result['fromEntityId']);
        $this->assertEquals('Project', $result['toEntityType']);
        $this->assertEquals('project-789', $result['toEntityId']);
        $this->assertEquals('has_access', $result['bindingType']);
        $this->assertEquals('{"access_level":"write","granted_by":"admin"}', $result['metadata']);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('updatedAt', $result);
    }

    /**
     * Test converting Weaviate object to BindingInterface.
     */
    public function testFromWeaviateObject(): void
    {
        $weaviateObject = [
            'properties' => [
                'bindingId' => 'test-binding-123',
                'fromEntityType' => 'Workspace',
                'fromEntityId' => 'workspace-456',
                'toEntityType' => 'Project',
                'toEntityId' => 'project-789',
                'bindingType' => 'has_access',
                'metadata' => '{"access_level":"write","granted_by":"admin"}',
                'createdAt' => '2024-01-15T10:30:00Z',
                'updatedAt' => '2024-01-15T10:30:00Z',
            ],
        ];

        // Mock metadata mapper
        $this->mockMetadataMapper
            ->expects($this->once())
            ->method('deserialize')
            ->with('{"access_level":"write","granted_by":"admin"}')
            ->willReturn(['access_level' => 'write', 'granted_by' => 'admin']);

        $result = $this->bindingMapper->fromWeaviateObject($weaviateObject);

        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals('test-binding-123', $result->getId());
        $this->assertEquals('Workspace', $result->getFromType());
        $this->assertEquals('workspace-456', $result->getFromId());
        $this->assertEquals('Project', $result->getToType());
        $this->assertEquals('project-789', $result->getToId());
        $this->assertEquals('has_access', $result->getType());
        $this->assertEquals(['access_level' => 'write', 'granted_by' => 'admin'], $result->getMetadata());
    }

    /**
     * Test converting Weaviate object without properties wrapper.
     */
    public function testFromWeaviateObjectWithoutPropertiesWrapper(): void
    {
        $weaviateObject = [
            'bindingId' => 'test-binding-456',
            'fromEntityType' => 'User',
            'fromEntityId' => 'user-123',
            'toEntityType' => 'Role',
            'toEntityId' => 'role-456',
            'bindingType' => 'has_role',
            'metadata' => '{"role_level":"admin"}',
            'createdAt' => '2024-01-15T10:30:00Z',
            'updatedAt' => '2024-01-15T10:30:00Z',
        ];

        // Mock metadata mapper
        $this->mockMetadataMapper
            ->expects($this->once())
            ->method('deserialize')
            ->with('{"role_level":"admin"}')
            ->willReturn(['role_level' => 'admin']);

        $result = $this->bindingMapper->fromWeaviateObject($weaviateObject);

        $this->assertInstanceOf(BindingInterface::class, $result);
        $this->assertEquals('test-binding-456', $result->getId());
        $this->assertEquals('User', $result->getFromType());
        $this->assertEquals('user-123', $result->getFromId());
        $this->assertEquals('Role', $result->getToType());
        $this->assertEquals('role-456', $result->getToId());
        $this->assertEquals('has_role', $result->getType());
        $this->assertEquals(['role_level' => 'admin'], $result->getMetadata());
    }

    /**
     * Test handling empty metadata.
     */
    public function testHandlingEmptyMetadata(): void
    {
        $binding = new Binding(
            id: 'test-binding-empty',
            fromType: 'Workspace',
            fromId: 'workspace-123',
            toType: 'Project',
            toId: 'project-456',
            type: 'has_access',
            metadata: [],
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        // Mock metadata mapper for empty metadata
        $this->mockMetadataMapper
            ->expects($this->once())
            ->method('serialize')
            ->with([])
            ->willReturn('{}');

        $result = $this->bindingMapper->toWeaviateProperties($binding);

        $this->assertEquals('{}', $result['metadata']);
    }

    /**
     * Test handling null metadata in Weaviate object.
     */
    public function testHandlingNullMetadataInWeaviateObject(): void
    {
        $weaviateObject = [
            'bindingId' => 'test-binding-null',
            'fromEntityType' => 'Workspace',
            'fromEntityId' => 'workspace-123',
            'toEntityType' => 'Project',
            'toEntityId' => 'project-456',
            'bindingType' => 'has_access',
            'metadata' => null,
            'createdAt' => '2024-01-15T10:30:00Z',
            'updatedAt' => '2024-01-15T10:30:00Z',
        ];

        // Mock metadata mapper for null metadata
        $this->mockMetadataMapper
            ->expects($this->once())
            ->method('deserialize')
            ->with(null)
            ->willReturn([]);

        $result = $this->bindingMapper->fromWeaviateObject($weaviateObject);

        $this->assertEquals([], $result->getMetadata());
    }

    /**
     * Test date formatting in toWeaviateProperties.
     */
    public function testDateFormattingInToWeaviateProperties(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15T10:30:00Z');
        $updatedAt = new \DateTimeImmutable('2024-01-15T11:45:00Z');

        $binding = new Binding(
            id: 'test-binding-dates',
            fromType: 'Workspace',
            fromId: 'workspace-123',
            toType: 'Project',
            toId: 'project-456',
            type: 'has_access',
            metadata: [],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->mockMetadataMapper
            ->method('serialize')
            ->willReturn('{}');

        $result = $this->bindingMapper->toWeaviateProperties($binding);

        $this->assertEquals('2024-01-15T10:30:00+00:00', $result['createdAt']);
        $this->assertEquals('2024-01-15T11:45:00+00:00', $result['updatedAt']);
    }

    /**
     * Test date parsing in fromWeaviateObject.
     */
    public function testDateParsingInFromWeaviateObject(): void
    {
        $weaviateObject = [
            'bindingId' => 'test-binding-dates',
            'fromEntityType' => 'Workspace',
            'fromEntityId' => 'workspace-123',
            'toEntityType' => 'Project',
            'toEntityId' => 'project-456',
            'bindingType' => 'has_access',
            'metadata' => '{}',
            'createdAt' => '2024-01-15T10:30:00+00:00',
            'updatedAt' => '2024-01-15T11:45:00+00:00',
        ];

        $this->mockMetadataMapper
            ->method('deserialize')
            ->willReturn([]);

        $result = $this->bindingMapper->fromWeaviateObject($weaviateObject);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getUpdatedAt());
        $this->assertEquals('2024-01-15T10:30:00+00:00', $result->getCreatedAt()->format('c'));
        $this->assertEquals('2024-01-15T11:45:00+00:00', $result->getUpdatedAt()->format('c'));
    }

    /**
     * Create a test binding for use in tests.
     */
    private function createTestBinding(): BindingInterface
    {
        return new Binding(
            id: 'test-binding-123',
            fromType: 'Workspace',
            fromId: 'workspace-456',
            toType: 'Project',
            toId: 'project-789',
            type: 'has_access',
            metadata: ['access_level' => 'write', 'granted_by' => 'admin'],
            createdAt: new \DateTimeImmutable('2024-01-15T10:30:00Z'),
            updatedAt: new \DateTimeImmutable('2024-01-15T10:30:00Z')
        );
    }
}
