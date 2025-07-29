<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Query;

use EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;

/**
 * Unit tests for BasicWeaviateQueryBuilder.
 */
class BasicWeaviateQueryBuilderTest extends TestCase
{
    /** @var MockObject&WeaviateClient */
    private MockObject $mockClient;

    /** @var MockObject&EntityInterface */
    private MockObject $mockEntity;

    private BasicWeaviateQueryBuilder $queryBuilder;

    private string $collectionName = 'TestBindings';

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(WeaviateClient::class);
        $this->mockEntity = $this->createMock(EntityInterface::class);
        $this->mockEntity->method('getId')->willReturn('entity-123');
        $this->mockEntity->method('getType')->willReturn('Workspace');

        $this->queryBuilder = new BasicWeaviateQueryBuilder($this->mockClient, $this->collectionName);
    }

    /**
     * Test that BasicWeaviateQueryBuilder implements QueryBuilderInterface.
     */
    public function testImplementsQueryBuilderInterface(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->queryBuilder);
    }

    /**
     * Test from() method with EntityInterface object.
     */
    public function testFromMethodWithEntityInterface(): void
    {
        $result = $this->queryBuilder->from($this->mockEntity);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result); // Should return new instance
        $this->assertEquals('entity-123', $result->getFromEntityId());
        $this->assertEquals('Workspace', $result->getFromEntityType());
    }

    /**
     * Test from() method with string entity type and ID.
     */
    public function testFromMethodWithStringEntityType(): void
    {
        $result = $this->queryBuilder->from('Project', 'project-456');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertEquals('project-456', $result->getFromEntityId());
        $this->assertEquals('Project', $result->getFromEntityType());
    }

    /**
     * Test from() method throws exception when entity ID is missing for string entity.
     */
    public function testFromMethodThrowsExceptionWhenEntityIdMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID is required when entity is provided as string');

        $this->queryBuilder->from('Project');
    }

    /**
     * Test to() method with EntityInterface object.
     */
    public function testToMethodWithEntityInterface(): void
    {
        $result = $this->queryBuilder->to($this->mockEntity);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertEquals('entity-123', $result->getToEntityId());
        $this->assertEquals('Workspace', $result->getToEntityType());
    }

    /**
     * Test type() method stores binding type and returns new instance.
     */
    public function testTypeMethodStoresBindingTypeAndReturnsNewInstance(): void
    {
        $result = $this->queryBuilder->type('has_access');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
        $this->assertEquals('has_access', $result->getBindingType());
    }

    /**
     * Test where() method with two parameters (field, value).
     */
    public function testWhereMethodWithTwoParameters(): void
    {
        $result = $this->queryBuilder->where('access_level', 'write');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals([
            'field' => 'access_level',
            'operator' => '=',
            'value' => 'write',
        ], $conditions[0]);
    }

    /**
     * Test where() method with three parameters (field, operator, value).
     */
    public function testWhereMethodWithThreeParameters(): void
    {
        $result = $this->queryBuilder->where('confidence_score', '>', 0.8);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals([
            'field' => 'confidence_score',
            'operator' => '>',
            'value' => 0.8,
        ], $conditions[0]);
    }

    /**
     * Test multiple where() conditions are accumulated.
     */
    public function testMultipleWhereConditionsAreAccumulated(): void
    {
        $result = $this->queryBuilder
            ->where('access_level', 'write')
            ->where('confidence_score', '>', 0.8)
            ->where('status', '!=', 'disabled');

        $conditions = $result->getWhereConditions();
        $this->assertCount(3, $conditions);

        $this->assertEquals('access_level', $conditions[0]['field']);
        $this->assertEquals('confidence_score', $conditions[1]['field']);
        $this->assertEquals('status', $conditions[2]['field']);
    }

    /**
     * Test limit() method stores limit value and returns new instance.
     */
    public function testLimitMethodStoresLimitAndReturnsNewInstance(): void
    {
        $result = $this->queryBuilder->limit(50);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
        $this->assertEquals(50, $result->getLimit());
    }

    /**
     * Test orderBy() method stores ordering criteria and returns new instance.
     */
    public function testOrderByMethodStoresOrderingAndReturnsNewInstance(): void
    {
        $result = $this->queryBuilder->orderBy('created_at', 'desc');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
        $ordering = $result->getOrderBy();
        $this->assertEquals([
            'field' => 'created_at',
            'direction' => 'desc',
        ], $ordering);
    }

    /**
     * Test get() method throws exception for Phase 1 limitations.
     */
    public function testGetMethodThrowsExceptionForPhase1Limitations(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Query execution requires Phase 2 client enhancements');

        $this->queryBuilder->get();
    }

    /**
     * Test count() method throws exception for Phase 1 limitations.
     */
    public function testCountMethodThrowsExceptionForPhase1Limitations(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Query count requires Phase 2 client enhancements');

        $this->queryBuilder->count();
    }

    /**
     * Test method chaining works correctly.
     */
    public function testMethodChaining(): void
    {
        $result = $this->queryBuilder
            ->from($this->mockEntity)
            ->type('has_access')
            ->where('access_level', 'write')
            ->where('confidence_score', '>', 0.8)
            ->limit(25)
            ->orderBy('created_at', 'desc');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);

        // Verify all criteria were stored
        $this->assertEquals('entity-123', $result->getFromEntityId());
        $this->assertEquals('has_access', $result->getBindingType());
        $this->assertCount(2, $result->getWhereConditions());
        $this->assertEquals(25, $result->getLimit());
        $orderBy = $result->getOrderBy();
        $this->assertNotNull($orderBy);
        $this->assertEquals('created_at', $orderBy['field']);
    }

    /**
     * Test Phase 2 placeholder methods for vector queries.
     */
    public function testPhase2PlaceholderMethods(): void
    {
        // Test nearText placeholder
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('nearText queries require Phase 2 vector search capabilities');

        $this->queryBuilder->nearText(['high priority'], 0.8);
    }

    /**
     * Test nearVector placeholder method.
     */
    public function testNearVectorPlaceholderMethod(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('nearVector queries require Phase 2 vector search capabilities');

        $vector = array_fill(0, 1536, 0.1); // Mock 1536-dimensional vector
        $this->queryBuilder->nearVector($vector, 0.9);
    }

    /**
     * Test that query builder can be reset.
     */
    public function testQueryBuilderCanBeReset(): void
    {
        // Build a query
        $builtQuery = $this->queryBuilder
            ->from($this->mockEntity)
            ->type('has_access')
            ->where('access_level', 'write')
            ->limit(10);

        // Reset the query
        $result = $builtQuery->reset();

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertNotSame($builtQuery, $result);
        $this->assertNull($result->getFromEntityId());
        $this->assertNull($result->getBindingType());
        $this->assertEmpty($result->getWhereConditions());
        $this->assertNull($result->getLimit());
    }

    /**
     * Test getCriteria() method returns query state.
     */
    public function testGetCriteriaReturnsQueryState(): void
    {
        $query = $this->queryBuilder
            ->from($this->mockEntity)
            ->type('has_access')
            ->where('access_level', 'write')
            ->limit(10);

        $criteria = $query->getCriteria();

        $this->assertIsArray($criteria);
        $this->assertEquals('entity-123', $criteria['from_entity_id']);
        $this->assertEquals('Workspace', $criteria['from_entity_type']);
        $this->assertEquals('has_access', $criteria['binding_type']);
        $this->assertCount(1, $criteria['where_conditions']);
        $this->assertEquals(10, $criteria['limit']);
    }

    /**
     * Test additional interface methods.
     */
    public function testAdditionalInterfaceMethods(): void
    {
        // Test first() method
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Query execution requires Phase 2 client enhancements');

        $this->queryBuilder->first();
    }

    /**
     * Test exists() method.
     */
    public function testExistsMethod(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Query existence check requires Phase 2 client enhancements');

        $this->queryBuilder->exists();
    }

    /**
     * Test whereIn() method.
     */
    public function testWhereInMethod(): void
    {
        $result = $this->queryBuilder->whereIn('status', ['active', 'pending']);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals('IN', $conditions[0]['operator']);
        $this->assertEquals(['active', 'pending'], $conditions[0]['value']);
    }

    /**
     * Test whereBetween() method.
     */
    public function testWhereBetweenMethod(): void
    {
        $result = $this->queryBuilder->whereBetween('score', 0.5, 1.0);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals('BETWEEN', $conditions[0]['operator']);
        $this->assertEquals([0.5, 1.0], $conditions[0]['value']);
    }

    /**
     * Test whereExists() method.
     */
    public function testWhereExistsMethod(): void
    {
        $result = $this->queryBuilder->whereExists('metadata');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['operator']);
        $this->assertNull($conditions[0]['value']);
    }

    /**
     * Test whereNull() method.
     */
    public function testWhereNullMethod(): void
    {
        $result = $this->queryBuilder->whereNull('deleted_at');

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $conditions = $result->getWhereConditions();
        $this->assertCount(1, $conditions);
        $this->assertEquals('IS_NULL', $conditions[0]['operator']);
        $this->assertNull($conditions[0]['value']);
    }

    /**
     * Test offset() method.
     */
    public function testOffsetMethod(): void
    {
        $result = $this->queryBuilder->offset(20);

        $this->assertInstanceOf(BasicWeaviateQueryBuilder::class, $result);
        $this->assertEquals(20, $result->getOffset());
    }

    /**
     * Test orWhere() method throws exception.
     */
    public function testOrWhereMethodThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('OR conditions require Phase 2 client enhancements');

        $this->queryBuilder->orWhere(function ($query) {
            return $query->where('status', 'active');
        });
    }
}
