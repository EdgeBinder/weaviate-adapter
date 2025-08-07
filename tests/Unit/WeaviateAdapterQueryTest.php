<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Contracts\QueryBuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collections;
use Weaviate\WeaviateClient;

/**
 * Unit tests for WeaviateAdapter query functionality.
 */
class WeaviateAdapterQueryTest extends TestCase
{
    /** @var MockObject&WeaviateClient */
    private MockObject $mockClient;

    /** @var MockObject&Collections */
    private MockObject $mockCollections;

    /** @var MockObject&QueryBuilderInterface */
    private MockObject $mockQueryBuilder;

    private WeaviateAdapter $adapter;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockCollections = $this->createMock(Collections::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilderInterface::class);

        // Set up the mock chain
        $this->mockClient->method('collections')->willReturn($this->mockCollections);
        $this->mockCollections->method('exists')->willReturn(true);

        $this->adapter = new WeaviateAdapter($this->mockClient, [
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => false],
        ]);
    }

    /**
     * Test executeQuery works with v0.5.0 API.
     */
    public function testExecuteQueryWorksWithV050Api(): void
    {
        // Mock the query chain for executeQuery
        $mockWeaviateQueryBuilder = $this->createMock(\Weaviate\Query\QueryBuilder::class);
        $mockWeaviateQueryBuilder->method('where')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('limit')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('fetchObjects')->willReturn([]);

        $mockCollection = $this->createMock(\Weaviate\Collections\Collection::class);
        $mockCollection->method('query')->willReturn($mockWeaviateQueryBuilder);

        $this->mockCollections->method('get')->willReturn($mockCollection);

        $result = $this->adapter->executeQuery($this->mockQueryBuilder);

        $this->assertIsArray($result);
    }

    /**
     * Test count works with v0.5.0 API.
     */
    public function testCountWorksWithV050Api(): void
    {
        // Mock the query chain for count (which uses executeQuery internally)
        $mockWeaviateQueryBuilder = $this->createMock(\Weaviate\Query\QueryBuilder::class);
        $mockWeaviateQueryBuilder->method('where')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('limit')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('returnProperties')->willReturnSelf();
        $mockWeaviateQueryBuilder->method('fetchObjects')->willReturn([]);

        $mockCollection = $this->createMock(\Weaviate\Collections\Collection::class);
        $mockCollection->method('query')->willReturn($mockWeaviateQueryBuilder);

        $this->mockCollections->method('get')->willReturn($mockCollection);

        $result = $this->adapter->count($this->mockQueryBuilder);

        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }
}
