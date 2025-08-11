<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Transformer for WeaviateAdapter.
 *
 * Converts EdgeBinder criteria objects into the array format
 * that the WeaviateAdapter expects for filtering.
 */
class WeaviateTransformer implements CriteriaTransformerInterface
{
    /**
     * Transform an entity criteria into array format.
     *
     * @param EntityCriteria $entity    The entity criteria to transform
     * @param string         $direction The direction ('from' or 'to')
     *
     * @return array<string, string> Array with entity type and ID
     */
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        $typeKey = 'from' === $direction ? 'fromType' : 'toType';
        $idKey = 'from' === $direction ? 'fromId' : 'toId';

        return [
            $typeKey => $entity->type,
            $idKey => $entity->id,
        ];
    }

    /**
     * Transform a where criteria into array format.
     *
     * @param WhereCriteria $where The where criteria to transform
     *
     * @return array<string, mixed> Array with field, operator, and value
     */
    public function transformWhere(WhereCriteria $where): array
    {
        return [
            'field' => $where->field,
            'operator' => $where->operator,
            'value' => $where->value,
        ];
    }

    /**
     * Transform a binding type into array format.
     *
     * @param string $type The binding type
     *
     * @return array<string, string> Array with type
     */
    public function transformBindingType(string $type): array
    {
        return [
            'type' => $type,
        ];
    }

    /**
     * Transform an order by criteria into array format.
     *
     * @param OrderByCriteria $orderBy The order by criteria to transform
     *
     * @return array<string, string> Array with field and direction
     */
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'field' => $orderBy->field,
            'direction' => $orderBy->direction,
        ];
    }

    /**
     * Combine multiple filters into a single query criteria array.
     *
     * @param array<mixed>        $filters   Array of filter arrays
     * @param array<array<mixed>> $orFilters Array of OR condition groups
     *
     * @return array<string, mixed> Combined query criteria
     */
    public function combineFilters(array $filters, array $orFilters = []): array
    {
        $criteria = [];
        $whereConditions = [];
        $orderByConditions = [];

        foreach ($filters as $filter) {
            // Merge entity filters (fromType, fromId, toType, toId)
            if (isset($filter['fromType'])) {
                $criteria['fromType'] = $filter['fromType'];
            }
            if (isset($filter['fromId'])) {
                $criteria['fromId'] = $filter['fromId'];
            }
            if (isset($filter['toType'])) {
                $criteria['toType'] = $filter['toType'];
            }
            if (isset($filter['toId'])) {
                $criteria['toId'] = $filter['toId'];
            }

            // Handle binding type
            if (isset($filter['type'])) {
                $criteria['type'] = $filter['type'];
            }

            // Collect order by conditions (check this first since they have both field and direction)
            if (isset($filter['field']) && isset($filter['direction'])) {
                $orderByConditions[] = $filter;
            }
            // Collect where conditions (only if not an orderBy condition)
            elseif (isset($filter['field'])) {
                $whereConditions[] = $filter;
            }
        }

        // Add collected conditions to criteria
        if (!empty($whereConditions)) {
            $criteria['where'] = $whereConditions;
        }

        if (!empty($orderByConditions)) {
            $criteria['orderBy'] = $orderByConditions;
        }

        // Add OR conditions
        if (!empty($orFilters)) {
            $criteria['orWhere'] = $orFilters;
        }

        return $criteria;
    }
}
