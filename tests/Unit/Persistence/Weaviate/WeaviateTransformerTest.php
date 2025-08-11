<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Persistence\Weaviate;

use EdgeBinder\Adapter\Weaviate\WeaviateTransformer;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\QueryCriteria;
use EdgeBinder\Query\WhereCriteria;
use PHPUnit\Framework\TestCase;

class WeaviateTransformerTest extends TestCase
{
    private WeaviateTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new WeaviateTransformer();
    }

    public function testTransformEntityFrom(): void
    {
        $entity = new EntityCriteria('User', 'user123');

        $result = $this->transformer->transformEntity($entity, 'from');

        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformEntityTo(): void
    {
        $entity = new EntityCriteria('Project', 'project456');

        $result = $this->transformer->transformEntity($entity, 'to');

        $expected = [
            'toType' => 'Project',
            'toId' => 'project456',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformWhere(): void
    {
        $where = new WhereCriteria('status', '=', 'active');

        $result = $this->transformer->transformWhere($where);

        $expected = [
            'field' => 'status',
            'operator' => '=',
            'value' => 'active',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformWhereWithComplexValue(): void
    {
        $where = new WhereCriteria('metadata.level', 'in', ['admin', 'moderator']);

        $result = $this->transformer->transformWhere($where);

        $expected = [
            'field' => 'metadata.level',
            'operator' => 'in',
            'value' => ['admin', 'moderator'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformBindingType(): void
    {
        $result = $this->transformer->transformBindingType('collaboration');

        $expected = [
            'type' => 'collaboration',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformOrderBy(): void
    {
        $orderBy = new OrderByCriteria('createdAt', 'desc');

        $result = $this->transformer->transformOrderBy($orderBy);

        $expected = [
            'field' => 'createdAt',
            'direction' => 'desc',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformOrderByDefaultDirection(): void
    {
        $orderBy = new OrderByCriteria('name');

        $result = $this->transformer->transformOrderBy($orderBy);

        $expected = [
            'field' => 'name',
            'direction' => 'asc',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithEntityFilters(): void
    {
        $filters = [
            ['fromType' => 'User', 'fromId' => 'user123'],
            ['toType' => 'Project', 'toId' => 'project456'],
            ['type' => 'collaboration'],
        ];

        $result = $this->transformer->combineFilters($filters);

        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123',
            'toType' => 'Project',
            'toId' => 'project456',
            'type' => 'collaboration',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithWhereConditions(): void
    {
        $filters = [
            ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ['field' => 'level', 'operator' => '>', 'value' => 5],
        ];

        $result = $this->transformer->combineFilters($filters);

        $expected = [
            'where' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ['field' => 'level', 'operator' => '>', 'value' => 5],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithOrderBy(): void
    {
        $filters = [
            ['field' => 'createdAt', 'direction' => 'desc'],
            ['field' => 'name', 'direction' => 'asc'],
        ];

        $result = $this->transformer->combineFilters($filters);

        $expected = [
            'orderBy' => [
                ['field' => 'createdAt', 'direction' => 'desc'],
                ['field' => 'name', 'direction' => 'asc'],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithOrConditions(): void
    {
        $filters = [
            ['field' => 'status', 'operator' => '=', 'value' => 'active'],
        ];
        $orFilters = [
            [
                ['field' => 'priority', 'operator' => '=', 'value' => 'high'],
                ['field' => 'urgent', 'operator' => '=', 'value' => true],
            ],
        ];

        $result = $this->transformer->combineFilters($filters, $orFilters);

        $expected = [
            'where' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ],
            'orWhere' => [
                [
                    ['field' => 'priority', 'operator' => '=', 'value' => 'high'],
                    ['field' => 'urgent', 'operator' => '=', 'value' => true],
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCompleteQueryCriteriaTransformation(): void
    {
        $criteria = new QueryCriteria(
            from: new EntityCriteria('User', 'user123'),
            type: 'collaboration',
            where: [
                new WhereCriteria('status', '=', 'active'),
                new WhereCriteria('metadata.level', '>', 5),
            ],
            orderBy: [
                new OrderByCriteria('createdAt', 'desc'),
            ]
        );

        $result = $criteria->transform($this->transformer);

        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123',
            'type' => 'collaboration',
            'where' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ['field' => 'metadata.level', 'operator' => '>', 'value' => 5],
            ],
            'orderBy' => [
                ['field' => 'createdAt', 'direction' => 'desc'],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithMixedFilters(): void
    {
        $filters = [
            ['fromType' => 'User', 'fromId' => 'user123'],
            ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ['field' => 'createdAt', 'direction' => 'desc'],
            ['type' => 'collaboration'],
        ];

        $result = $this->transformer->combineFilters($filters);

        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123',
            'type' => 'collaboration',
            'where' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ],
            'orderBy' => [
                ['field' => 'createdAt', 'direction' => 'desc'],
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCombineFiltersWithEmptyFilters(): void
    {
        $result = $this->transformer->combineFilters([]);

        $this->assertEquals([], $result);
    }
}
