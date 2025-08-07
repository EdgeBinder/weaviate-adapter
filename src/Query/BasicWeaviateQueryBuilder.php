<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Query;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use Weaviate\WeaviateClient;

/**
 * Basic Weaviate query builder for Phase 1 implementation.
 *
 * This query builder provides the EdgeBinder query interface while working
 * within the limitations of the current Weaviate client. It stores query
 * criteria but execution requires Phase 2 client enhancements.
 *
 * Phase 1: Basic query building with execution placeholders
 * Phase 2: Full query execution with vector search capabilities
 */
class BasicWeaviateQueryBuilder implements QueryBuilderInterface
{
    private WeaviateClient $client;

    private string $collectionName;

    private ?\Closure $executeCallback = null;

    // Query criteria storage
    private ?string $fromEntityId = null;

    private ?string $fromEntityType = null;

    private ?string $toEntityId = null;

    private ?string $toEntityType = null;

    private ?string $bindingType = null;

    private array $whereConditions = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private ?array $orderBy = null;

    public function __construct(
        WeaviateClient $client,
        string $collectionName,
        ?string $fromEntityId = null,
        ?string $fromEntityType = null,
        ?string $toEntityId = null,
        ?string $toEntityType = null,
        ?string $bindingType = null,
        array $whereConditions = [],
        ?int $limit = null,
        ?int $offset = null,
        ?array $orderBy = null
    ) {
        $this->client = $client;
        $this->collectionName = $collectionName;
        $this->fromEntityId = $fromEntityId;
        $this->fromEntityType = $fromEntityType;
        $this->toEntityId = $toEntityId;
        $this->toEntityType = $toEntityType;
        $this->bindingType = $bindingType;
        $this->whereConditions = $whereConditions;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->orderBy = $orderBy;
    }

    /**
     * Set the execute callback for query execution.
     */
    public function setExecuteCallback(\Closure $callback): self
    {
        $this->executeCallback = $callback;

        return $this;
    }

    /**
     * Filter bindings by source entity.
     *
     * @return static
     */
    public function from(object|string $entity, ?string $entityId = null): static
    {
        if (is_string($entity)) {
            if ($entityId === null) {
                throw new \InvalidArgumentException('Entity ID is required when entity is provided as string');
            }
            $fromEntityType = $entity;
            $fromEntityId = $entityId;
        } else {
            $fromEntityId = $this->extractEntityId($entity);
            $fromEntityType = $this->extractEntityType($entity);
        }

        return new self(
            $this->client,
            $this->collectionName,
            $fromEntityId,
            $fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $this->whereConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings by target entity.
     */
    public function to(object|string $entity, ?string $entityId = null): static
    {
        if (is_string($entity)) {
            if ($entityId === null) {
                throw new \InvalidArgumentException('Entity ID is required when entity is provided as string');
            }
            $toEntityType = $entity;
            $toEntityId = $entityId;
        } else {
            $toEntityId = $this->extractEntityId($entity);
            $toEntityType = $this->extractEntityType($entity);
        }

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $toEntityId,
            $toEntityType,
            $this->bindingType,
            $this->whereConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings by binding type.
     */
    public function type(string $type): static
    {
        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $type,
            $this->whereConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Add a where condition to the query.
     */
    public function where(string $field, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            // Two-parameter form: where(field, value)
            $actualOperator = '=';
            $actualValue = $operator;
        } else {
            // Three-parameter form: where(field, operator, value)
            $actualOperator = (string) $operator;
            $actualValue = $value;
        }

        $newConditions = $this->whereConditions;
        $newConditions[] = [
            'field' => $field,
            'operator' => $actualOperator,
            'value' => $actualValue,
        ];

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $newConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings where metadata field is in a list of values.
     */
    public function whereIn(string $field, array $values): static
    {
        $newConditions = $this->whereConditions;
        $newConditions[] = [
            'field' => $field,
            'operator' => 'IN',
            'value' => $values,
        ];

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $newConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings where metadata field is between two values.
     */
    public function whereBetween(string $field, mixed $min, mixed $max): static
    {
        $newConditions = $this->whereConditions;
        $newConditions[] = [
            'field' => $field,
            'operator' => 'BETWEEN',
            'value' => [$min, $max],
        ];

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $newConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings where metadata field exists.
     */
    public function whereExists(string $field): static
    {
        $newConditions = $this->whereConditions;
        $newConditions[] = [
            'field' => $field,
            'operator' => 'EXISTS',
            'value' => null,
        ];

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $newConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Filter bindings where metadata field is null or doesn't exist.
     */
    public function whereNull(string $field): static
    {
        $newConditions = $this->whereConditions;
        $newConditions[] = [
            'field' => $field,
            'operator' => 'IS_NULL',
            'value' => null,
        ];

        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $newConditions,
            $this->limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Add OR condition group.
     */
    public function orWhere(callable $callback): static
    {
        throw new \BadMethodCallException(
            'OR conditions require Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports complex query operations.'
        );
    }

    /**
     * Order results by a field.
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $this->whereConditions,
            $this->limit,
            $this->offset,
            [
                'field' => $field,
                'direction' => strtolower($direction),
            ]
        );
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): static
    {
        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $this->whereConditions,
            $limit,
            $this->offset,
            $this->orderBy
        );
    }

    /**
     * Skip a number of results (pagination).
     */
    public function offset(int $offset): static
    {
        return new self(
            $this->client,
            $this->collectionName,
            $this->fromEntityId,
            $this->fromEntityType,
            $this->toEntityId,
            $this->toEntityType,
            $this->bindingType,
            $this->whereConditions,
            $this->limit,
            $offset,
            $this->orderBy
        );
    }

    /**
     * Execute the query and return matching bindings.
     *
     * Uses the v0.5.0 Weaviate client query API via the adapter's executeQuery method.
     */
    public function get(): array
    {
        if ($this->executeCallback === null) {
            throw new \BadMethodCallException(
                'Query execution callback not set. Use setExecuteCallback() to enable query execution.'
            );
        }

        return ($this->executeCallback)($this);
    }

    /**
     * Execute the query and return the first matching binding.
     *
     * Phase 1: Not supported - requires Phase 2 client enhancements.
     */
    public function first(): ?BindingInterface
    {
        throw new \BadMethodCallException(
            'Query execution requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Count the number of bindings matching the query.
     *
     * Phase 1: Not supported - requires Phase 2 client enhancements.
     */
    public function count(): int
    {
        throw new \BadMethodCallException(
            'Query count requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Check if any bindings match the query.
     *
     * Phase 1: Not supported - requires Phase 2 client enhancements.
     */
    public function exists(): bool
    {
        throw new \BadMethodCallException(
            'Query existence check requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Get the query criteria for storage adapter execution.
     */
    public function getCriteria(): array
    {
        return [
            'from_entity_id' => $this->fromEntityId,
            'from_entity_type' => $this->fromEntityType,
            'to_entity_id' => $this->toEntityId,
            'to_entity_type' => $this->toEntityType,
            'binding_type' => $this->bindingType,
            'where_conditions' => $this->whereConditions,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'order_by' => $this->orderBy,
        ];
    }

    /**
     * Phase 2 placeholder: Add semantic text similarity to the query.
     */
    public function nearText(array $concepts, float $certainty = 0.7): static
    {
        throw new \BadMethodCallException(
            'nearText queries require Phase 2 vector search capabilities. ' .
            'This feature will be available when the Zestic client supports semantic search operations.'
        );
    }

    /**
     * Phase 2 placeholder: Add vector similarity to the query.
     */
    public function nearVector(array $vector, float $certainty = 0.7): static
    {
        throw new \BadMethodCallException(
            'nearVector queries require Phase 2 vector search capabilities. ' .
            'This feature will be available when the Zestic client supports vector similarity operations.'
        );
    }

    /**
     * Reset the query builder to its initial state.
     */
    public function reset(): static
    {
        return new self($this->client, $this->collectionName);
    }

    /**
     * Extract entity ID from an entity object.
     */
    private function extractEntityId(object $entity): string
    {
        if ($entity instanceof EntityInterface) {
            return $entity->getId();
        }

        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        throw new \InvalidArgumentException(
            'Entity must implement EntityInterface or have a getId() method'
        );
    }

    /**
     * Extract entity type from an entity object.
     */
    private function extractEntityType(object $entity): string
    {
        if ($entity instanceof EntityInterface) {
            return $entity->getType();
        }

        if (method_exists($entity, 'getType')) {
            return $entity->getType();
        }

        throw new \InvalidArgumentException(
            'Entity must implement EntityInterface or have a getType() method'
        );
    }

    // Getter methods for testing

    public function getFromEntityId(): ?string
    {
        return $this->fromEntityId;
    }

    public function getFromEntityType(): ?string
    {
        return $this->fromEntityType;
    }

    public function getToEntityId(): ?string
    {
        return $this->toEntityId;
    }

    public function getToEntityType(): ?string
    {
        return $this->toEntityType;
    }

    public function getBindingType(): ?string
    {
        return $this->bindingType;
    }

    public function getWhereConditions(): array
    {
        return $this->whereConditions;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getOrderBy(): ?array
    {
        return $this->orderBy;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function getClient(): WeaviateClient
    {
        return $this->client;
    }
}
