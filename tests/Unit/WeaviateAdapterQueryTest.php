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
     * Test executeQuery throws exception (Phase 1 limitation).
     */
    public function testExecuteQueryThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('executeQuery requires Phase 2 client enhancements');

        $this->adapter->executeQuery($this->mockQueryBuilder);
    }

    /**
     * Test count throws exception (Phase 1 limitation).
     */
    public function testCountThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('count requires Phase 2 client enhancements');

        $this->adapter->count($this->mockQueryBuilder);
    }
}
