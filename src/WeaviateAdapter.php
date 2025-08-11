<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Mapping\BindingMapper;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use EdgeBinder\Adapter\Weaviate\Query\QueryResult;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryResultInterface;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Query\QueryCriteria;
use Weaviate\Query\Filter;
use Weaviate\WeaviateClient;

/**
 * Weaviate adapter for EdgeBinder persistence.
 *
 * This adapter provides EdgeBinder integration with Weaviate vector database,
 * supporting both basic relationship storage and advanced vector operations.
 *
 * Phase 1: Basic CRUD operations with current Zestic client capabilities
 * Phase 2: Vector similarity search (requires client enhancements)
 */
class WeaviateAdapter implements PersistenceAdapterInterface
{
    private WeaviateClient $client;

    private array $config;

    private string $collectionName;

    private BindingMapper $bindingMapper;

    private WeaviateTransformer $transformer;

    /**
     * Internal storage for testing purposes.
     * Some tests use reflection to access this property.
     *
     * @var array<string, BindingInterface>
     * @phpstan-ignore-next-line property.onlyWritten
     */
    private array $bindings = [];

    public function __construct(
        WeaviateClient $client,
        array $config = [],
        ?BindingMapper $bindingMapper = null
    ) {
        $this->client = $client;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->collectionName = $this->config['collection_name'];

        // Initialize mappers
        $metadataMapper = new MetadataMapper();
        $this->bindingMapper = $bindingMapper ?? new BindingMapper($metadataMapper);
        $this->transformer = new WeaviateTransformer();
    }

    /**
     * Store a binding in Weaviate.
     */
    public function store(BindingInterface $binding): void
    {
        try {
            // Validate metadata before storing
            $this->validateAndNormalizeMetadata($binding->getMetadata());

            $collection = $this->client->collections()->get($this->collectionName);
            $properties = $this->bindingMapper->toWeaviateProperties($binding);

            // Add the Weaviate UUID for the object
            $weaviateId = $this->generateUuidFromString($binding->getId());
            $properties['id'] = $weaviateId;

            $result = $collection->data()->create($properties);

            if (!$result) {
                throw WeaviateException::clientError('store', 'Failed to store binding');
            }
        } catch (\Exception $e) {
            // Let EdgeBinder exceptions bubble up unchanged - they represent business logic issues
            if ($e instanceof WeaviateException ||
                $e instanceof InvalidMetadataException ||
                $e instanceof BindingNotFoundException ||
                $e instanceof EntityExtractionException) {
                throw $e;
            }
            // Only wrap infrastructure/Weaviate-specific errors
            throw WeaviateException::serverError('store', 'Storage operation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Find a binding by its unique identifier.
     */
    public function find(string $bindingId): ?BindingInterface
    {
        try {
            $collection = $this->client->collections()->get($this->collectionName);
            $weaviateId = $this->generateUuidFromString($bindingId);
            $result = $collection->data()->get($weaviateId);

            return $this->bindingMapper->fromWeaviateObject($result);
        } catch (\Exception $e) {
            // If the object is not found, Weaviate typically returns a 404 error
            // We'll treat this as a null result rather than an exception
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                return null;
            }
            throw WeaviateException::serverError('find', 'Find operation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Delete a binding from storage.
     */
    public function delete(string $bindingId): void
    {
        try {
            // First check if the binding exists
            $existing = $this->find($bindingId);
            if ($existing === null) {
                throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
            }

            $collection = $this->client->collections()->get($this->collectionName);
            $weaviateId = $this->generateUuidFromString($bindingId);
            $result = $collection->data()->delete($weaviateId);

            // Note: Weaviate delete may return true even if object doesn't exist
            // We already checked existence above, so we can proceed
        } catch (BindingNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Check if it's a 404/not found error
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
            }
            throw WeaviateException::serverError('delete', 'Delete operation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Update a binding's metadata.
     */
    public function updateMetadata(string $bindingId, array $metadata): void
    {
        try {
            $normalizedMetadata = $this->validateAndNormalizeMetadata($metadata);

            $collection = $this->client->collections()->get($this->collectionName);

            $updateData = [
                'metadata' => $this->bindingMapper->getMetadataMapper()->serialize($normalizedMetadata),
                'updatedAt' => (new \DateTimeImmutable())->format('c'),
            ];

            $weaviateId = $this->generateUuidFromString($bindingId);
            $result = $collection->data()->update($weaviateId, $updateData);

            // The update method returns the updated object, so if we get here, it succeeded
        } catch (InvalidMetadataException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Check if it's a 404/not found error
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                throw new BindingNotFoundException("Binding with ID '{$bindingId}' not found");
            }
            if ($e instanceof WeaviateException) {
                throw $e;
            }
            throw WeaviateException::serverError('updateMetadata', 'Metadata update failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Find all bindings involving a specific entity.
     *
     * Finds bindings where the entity appears as either the source or target.
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        try {
            $collection = $this->client->collections()->get($this->collectionName);

            // Create filter for entity as source OR target
            $filter = Filter::anyOf([
                Filter::allOf([
                    Filter::byProperty('fromEntityType')->equal($entityType),
                    Filter::byProperty('fromEntityId')->equal($entityId),
                ]),
                Filter::allOf([
                    Filter::byProperty('toEntityType')->equal($entityType),
                    Filter::byProperty('toEntityId')->equal($entityId),
                ]),
            ]);

            // Execute the query
            $results = $collection->query()
                ->where($filter)
                ->returnProperties(['bindingId', 'fromEntityType', 'fromEntityId', 'toEntityType', 'toEntityId', 'bindingType', 'metadata', 'createdAt', 'updatedAt'])
                ->fetchObjects();

            // Convert results back to EdgeBinder format
            return $this->convertWeaviateResultsToBindings($results);
        } catch (\Exception $e) {
            throw WeaviateException::serverError('findByEntity', 'Find by entity failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Generate a deterministic UUID from a string.
     * This ensures the same binding ID always maps to the same Weaviate UUID.
     */
    private function generateUuidFromString(string $input): string
    {
        // Use MD5 hash to create a deterministic UUID
        $hash = md5($input);

        // Format as UUID v4
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            '4' . substr($hash, 13, 3), // Version 4
            dechex(hexdec(substr($hash, 16, 1)) & 0x3 | 0x8) . substr($hash, 17, 3), // Variant
            substr($hash, 20, 12)
        );
    }

    /**
     * Get default configuration options.
     */
    private function getDefaultConfig(): array
    {
        return [
            'collection_name' => 'EdgeBindings',
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'text2vec-openai',
            ],
            'vectorizer' => [
                'provider' => 'openai',
                'model' => 'text-embedding-ada-002',
            ],
            'performance' => [
                'batch_size' => 100,
                'vector_cache_ttl' => 3600,
            ],
        ];
    }

    /**
     * Initialize Weaviate schema if auto-create is enabled.
     * This should be called explicitly when needed, typically in test setup or application initialization.
     */
    public function initializeSchema(): void
    {
        if ($this->config['schema']['auto_create']) {
            $this->ensureCollectionExists();
        }
    }

    /**
     * Ensure the collection exists in Weaviate.
     */
    private function ensureCollectionExists(): void
    {
        try {
            if (!$this->client->collections()->exists($this->collectionName)) {
                $this->createCollection();
            }
        } catch (\Exception $e) {
            throw SchemaException::collectionCreationFailed(
                $this->collectionName,
                $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Create the collection in Weaviate.
     */
    private function createCollection(): void
    {
        $schemaDefinition = [
            'properties' => [
                [
                    'name' => 'bindingId',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'fromEntityType',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'fromEntityId',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'toEntityType',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'toEntityId',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'bindingType',
                    'dataType' => ['text'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'metadata',
                    'dataType' => ['text'], // Store as JSON string
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'createdAt',
                    'dataType' => ['date'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
                [
                    'name' => 'updatedAt',
                    'dataType' => ['date'],
                    'invertedIndexConfig' => ['indexNullState' => true],
                ],
            ],
            'vectorizer' => 'none', // Phase 1: Disable vectorizer
        ];

        $this->client->collections()->create($this->collectionName, $schemaDefinition);
    }

    /**
     * Extract the unique identifier from an entity object.
     */
    public function extractEntityId(object $entity): string
    {
        // Try EntityInterface first
        if ($entity instanceof EntityInterface) {
            return $entity->getId();
        }

        // Try getId() method
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            // Convert to string and check if not empty
            $stringId = (string) $id;
            if (!empty($stringId)) {
                return $stringId;
            }
        }

        // Try id property
        try {
            if (property_exists($entity, 'id')) {
                $id = $entity->id;
                // Convert to string and check if not empty
                $stringId = (string) $id;
                if (!empty($stringId)) {
                    return $stringId;
                }
            }
        } catch (\Exception $e) {
            // Ignore reflection errors and fall through to object hash
        }

        // Fall back to object hash as last resort
        return 'obj_' . spl_object_hash($entity);
    }

    /**
     * Extract the type identifier from an entity object.
     */
    public function extractEntityType(object $entity): string
    {
        // Try EntityInterface first
        if ($entity instanceof EntityInterface) {
            return $entity->getType();
        }

        // Try getType() method
        if (method_exists($entity, 'getType')) {
            $type = $entity->getType();
            // Only use if it returns a non-empty string
            if (is_string($type) && !empty($type)) {
                return $type;
            }
        }

        // Fallback to class name
        $className = get_class($entity);

        // Handle anonymous classes specially
        if (str_contains($className, 'class@anonymous')) {
            return $className;
        }

        // For regular classes, return just the class name without namespace
        return basename(str_replace('\\', '/', $className));
    }

    /**
     * Validate and normalize metadata for storage.
     */
    public function validateAndNormalizeMetadata(array $metadata): array
    {
        // Check for non-string keys
        foreach (array_keys($metadata) as $key) {
            if (!is_string($key)) {
                throw new InvalidMetadataException('Metadata keys must be strings');
            }
        }

        // Check for invalid types and nesting depth
        $this->validateMetadataTypes($metadata, '', 0);

        // Check size limits
        $serialized = json_encode($metadata);
        if ($serialized === false) {
            throw new InvalidMetadataException('Metadata cannot be serialized to JSON');
        }

        $size = strlen($serialized);
        $maxSize = 64 * 1024; // 64KB limit
        if ($size > $maxSize) {
            throw new InvalidMetadataException(
                "Metadata size ({$size} bytes) exceeds limit ({$maxSize} bytes)"
            );
        }

        return $metadata;
    }

    /**
     * Recursively validate metadata types and nesting depth.
     */
    private function validateMetadataTypes(array $metadata, string $path = '', int $depth = 0): void
    {
        // Check nesting depth (max 10 levels)
        if ($depth >= 10) {
            throw new InvalidMetadataException('Metadata nesting too deep (max 10 levels)');
        }

        foreach ($metadata as $key => $value) {
            // Only require string keys at the top level (depth 0)
            // Nested arrays can have numeric keys for list-like data
            if ($depth === 0 && !is_string($key)) {
                throw new InvalidMetadataException('Metadata keys must be strings');
            }

            $currentPath = $path ? "{$path}.{$key}" : (string) $key;

            if (is_resource($value)) {
                throw new InvalidMetadataException('Metadata cannot contain resources');
            }

            if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                throw new InvalidMetadataException('Metadata can only contain DateTime objects');
            }

            if (is_array($value)) {
                $this->validateMetadataTypes($value, $currentPath, $depth + 1);
            }
        }
    }

    /**
     * Find bindings between two specific entities.
     *
     * Finds bindings that connect the specified source and target entities.
     */
    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        try {
            $collection = $this->client->collections()->get($this->collectionName);

            // Create filter for specific from/to entities
            $filters = [
                Filter::byProperty('fromEntityType')->equal($fromType),
                Filter::byProperty('fromEntityId')->equal($fromId),
                Filter::byProperty('toEntityType')->equal($toType),
                Filter::byProperty('toEntityId')->equal($toId),
            ];

            // Add binding type filter if specified
            if ($bindingType !== null) {
                $filters[] = Filter::byProperty('bindingType')->equal($bindingType);
            }

            $filter = Filter::allOf($filters);

            // Execute the query
            $results = $collection->query()
                ->where($filter)
                ->returnProperties(['bindingId', 'fromEntityType', 'fromEntityId', 'toEntityType', 'toEntityId', 'bindingType', 'metadata', 'createdAt', 'updatedAt'])
                ->fetchObjects();

            // Convert results back to EdgeBinder format
            return $this->convertWeaviateResultsToBindings($results);
        } catch (\Exception $e) {
            throw WeaviateException::serverError('findBetweenEntities', 'Find between entities failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Execute a query and return matching bindings.
     *
     * Uses the v0.6.0 criteria transformer pattern for lightweight query execution.
     */
    public function executeQuery(QueryCriteria $criteria): QueryResultInterface
    {
        try {
            // Transform QueryCriteria to the array format this adapter expects
            $criteriaArray = $criteria->transform($this->transformer);
            $results = $this->filterBindings($criteriaArray);

            // Apply ordering
            if (isset($criteriaArray['orderBy'])) {
                foreach ($criteriaArray['orderBy'] as $orderClause) {
                    $results = $this->applyOrdering($results, $orderClause);
                }
            }

            // Apply pagination
            if (isset($criteriaArray['offset']) || isset($criteriaArray['limit'])) {
                $offset = $criteriaArray['offset'] ?? 0;
                $limit = $criteriaArray['limit'] ?? null;
                $results = array_slice($results, $offset, $limit);
            }

            return new QueryResult(array_values($results));
        } catch (\Exception $e) {
            throw WeaviateException::serverError('executeQuery', 'Query execution failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Count bindings matching a query.
     *
     * Uses the v0.6.0 criteria transformer pattern for lightweight count execution.
     */
    public function count(QueryCriteria $criteria): int
    {
        try {
            // Transform QueryCriteria to the array format this adapter expects
            $criteriaArray = $criteria->transform($this->transformer);
            $results = $this->filterBindings($criteriaArray);

            return count($results);
        } catch (\Exception $e) {
            throw WeaviateException::serverError('count', 'Count query failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Delete all bindings involving a specific entity.
     *
     * Finds all bindings where the entity appears as either source or target,
     * then deletes them one by one.
     */
    public function deleteByEntity(string $entityType, string $entityId): int
    {
        try {
            // Find all bindings involving this entity
            $bindings = $this->findByEntity($entityType, $entityId);
            $deletedCount = 0;

            // Delete each binding individually
            foreach ($bindings as $binding) {
                try {
                    $this->delete($binding->getId());
                    ++$deletedCount;
                } catch (\EdgeBinder\Exception\BindingNotFoundException $e) {
                    // Binding was already deleted by another process - this is acceptable
                    // Continue without incrementing the count
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            throw WeaviateException::serverError('deleteByEntity', 'Delete by entity failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Convert Weaviate v0.5.0 results to EdgeBinder Binding format.
     */
    protected function convertWeaviateResultsToBindings(array $results): array
    {
        $bindings = [];

        foreach ($results as $result) {
            try {
                $binding = $this->bindingMapper->fromWeaviateObject($result);
                $bindings[] = $binding;
            } catch (\Exception $e) {
                // Log error but continue processing other results
                error_log('Failed to convert Weaviate result to binding: ' . $e->getMessage());
            }
        }

        return $bindings;
    }

    /**
     * Get all bindings from Weaviate for filtering.
     *
     * @return BindingInterface[] All bindings in the collection
     */
    private function getAllBindings(): array
    {
        try {
            $collection = $this->client->collections()->get($this->collectionName);
            $results = $collection->query()
                ->returnProperties([
                    'bindingId', 'fromEntityType', 'fromEntityId',
                    'toEntityType', 'toEntityId', 'bindingType',
                    'metadata', 'createdAt', 'updatedAt',
                ])
                ->fetchObjects();

            return $this->convertWeaviateResultsToBindings($results);
        } catch (\Exception $e) {
            throw WeaviateException::serverError('getAllBindings', 'Failed to fetch all bindings: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Filter bindings based on query criteria.
     *
     * @param array<string, mixed> $criteria Query criteria from transformer
     *
     * @return BindingInterface[] Filtered bindings
     */
    private function filterBindings(array $criteria): array
    {
        $results = $this->getAllBindings();

        // Filter by from entity
        if (isset($criteria['fromType']) && isset($criteria['fromId'])) {
            $fromType = $criteria['fromType'];
            $fromId = $criteria['fromId'];
            $results = array_filter(
                $results,
                fn (BindingInterface $binding) => $binding->getFromType() === $fromType && $binding->getFromId() === $fromId
            );
        }

        // Filter by to entity
        if (isset($criteria['toType']) && isset($criteria['toId'])) {
            $toType = $criteria['toType'];
            $toId = $criteria['toId'];
            $results = array_filter(
                $results,
                fn (BindingInterface $binding) => $binding->getToType() === $toType && $binding->getToId() === $toId
            );
        }

        // Filter by binding type
        if (isset($criteria['type'])) {
            $type = $criteria['type'];
            $results = array_filter(
                $results,
                fn (BindingInterface $binding) => $binding->getType() === $type
            );
        }

        // Apply where conditions
        if (isset($criteria['where'])) {
            foreach ($criteria['where'] as $condition) {
                $results = $this->applyWhereCondition($results, $condition);
            }
        }

        // Apply OR conditions
        if (isset($criteria['orWhere'])) {
            foreach ($criteria['orWhere'] as $orGroup) {
                $orResults = $this->getAllBindings();
                foreach ($orGroup as $condition) {
                    $orResults = $this->applyWhereCondition($orResults, $condition);
                }
                // Merge OR results with main results
                $results = array_merge($results, $orResults);
                $results = array_unique($results, SORT_REGULAR);
            }
        }

        return array_values($results);
    }

    /**
     * Apply a single where condition to filter bindings.
     *
     * @param BindingInterface[]   $bindings  Bindings to filter
     * @param array<string, mixed> $condition Where condition
     *
     * @return BindingInterface[] Filtered bindings
     */
    private function applyWhereCondition(array $bindings, array $condition): array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        return array_filter($bindings, function (BindingInterface $binding) use ($field, $operator, $value) {
            $fieldValue = $this->getFieldValue($binding, $field);

            return match ($operator) {
                '=' => $fieldValue === $value,
                '!=' => $fieldValue !== $value,
                '>' => $fieldValue > $value,
                '>=' => $fieldValue >= $value,
                '<' => $fieldValue < $value,
                '<=' => $fieldValue <= $value,
                'in' => is_array($value) && in_array($fieldValue, $value, true),
                'notIn' => is_array($value) && !in_array($fieldValue, $value, true),
                'between' => is_array($value) && 2 === count($value)
                           && $fieldValue >= $value[0] && $fieldValue <= $value[1],
                'exists' => $this->fieldExists($binding, $field),
                'null' => !$this->fieldExists($binding, $field) || null === $fieldValue,
                'notNull' => $this->fieldExists($binding, $field) && null !== $fieldValue,
                default => throw new WeaviateException('query', "Unsupported operator: {$operator}"),
            };
        });
    }

    /**
     * Apply ordering to bindings.
     *
     * @param BindingInterface[]   $bindings Bindings to order
     * @param array<string, mixed> $orderBy  Order criteria
     *
     * @return BindingInterface[] Ordered bindings
     */
    private function applyOrdering(array $bindings, array $orderBy): array
    {
        $field = $orderBy['field'];
        $direction = strtolower($orderBy['direction'] ?? 'asc');

        usort($bindings, function (BindingInterface $a, BindingInterface $b) use ($field, $direction) {
            $valueA = $this->getOrderingValue($a, $field);
            $valueB = $this->getOrderingValue($b, $field);

            $comparison = $valueA <=> $valueB;

            return 'desc' === $direction ? -$comparison : $comparison;
        });

        return $bindings;
    }

    /**
     * Get value for ordering from binding.
     *
     * @param BindingInterface $binding The binding
     * @param string           $field   The field to get value for
     *
     * @return mixed The value for ordering
     */
    private function getOrderingValue(BindingInterface $binding, string $field): mixed
    {
        return match ($field) {
            'id' => $binding->getId(),
            'fromType' => $binding->getFromType(),
            'fromId' => $binding->getFromId(),
            'toType' => $binding->getToType(),
            'toId' => $binding->getToId(),
            'type' => $binding->getType(),
            'createdAt' => $binding->getCreatedAt()->getTimestamp(),
            'updatedAt' => $binding->getUpdatedAt()->getTimestamp(),
            default => $binding->getMetadata()[$field] ?? null,
        };
    }

    /**
     * Get field value from binding, supporting nested paths like 'metadata.level'.
     */
    private function getFieldValue(BindingInterface $binding, string $field): mixed
    {
        // Handle metadata fields
        if (str_starts_with($field, 'metadata.')) {
            $metadataKey = substr($field, 9); // Remove 'metadata.' prefix
            $metadata = $binding->getMetadata();

            return array_key_exists($metadataKey, $metadata) ? $metadata[$metadataKey] : null;
        }

        // Handle direct binding properties
        return match ($field) {
            'id' => $binding->getId(),
            'fromType' => $binding->getFromType(),
            'fromId' => $binding->getFromId(),
            'toType' => $binding->getToType(),
            'toId' => $binding->getToId(),
            'type' => $binding->getType(),
            'createdAt' => $binding->getCreatedAt()->getTimestamp(),
            'updatedAt' => $binding->getUpdatedAt()->getTimestamp(),
            default => $binding->getMetadata()[$field] ?? null,
        };
    }

    /**
     * Check if field exists in binding, supporting nested paths.
     */
    private function fieldExists(BindingInterface $binding, string $field): bool
    {
        // Handle metadata fields
        if (str_starts_with($field, 'metadata.')) {
            $metadataKey = substr($field, 9); // Remove 'metadata.' prefix

            return array_key_exists($metadataKey, $binding->getMetadata());
        }

        // Handle direct binding properties
        return match ($field) {
            'id', 'fromType', 'fromId', 'toType', 'toId', 'type', 'createdAt', 'updatedAt' => true,
            default => array_key_exists($field, $binding->getMetadata()),
        };
    }
}
