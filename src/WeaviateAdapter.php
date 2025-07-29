<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Mapping\BindingMapper;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use EdgeBinder\Adapter\Weaviate\Query\BasicWeaviateQueryBuilder;
use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
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

        $this->initializeSchema();
    }

    /**
     * Store a binding in Weaviate.
     */
    public function store(BindingInterface $binding): void
    {
        try {
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
            if ($e instanceof WeaviateException) {
                throw $e;
            }
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
            $collection = $this->client->collections()->get($this->collectionName);
            $weaviateId = $this->generateUuidFromString($bindingId);
            $result = $collection->data()->delete($weaviateId);

            if (!$result) {
                throw WeaviateException::clientError('delete', "Failed to delete binding: {$bindingId}");
            }
        } catch (\Exception $e) {
            if ($e instanceof WeaviateException) {
                throw $e;
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
                'metadata' => json_encode($normalizedMetadata),
                'updatedAt' => (new \DateTimeImmutable())->format('c'),
            ];

            $weaviateId = $this->generateUuidFromString($bindingId);
            $result = $collection->data()->update($weaviateId, $updateData);

            // The update method returns the updated object, so if we get here, it succeeded
        } catch (InvalidMetadataException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($e instanceof WeaviateException) {
                throw $e;
            }
            throw WeaviateException::serverError('updateMetadata', 'Metadata update failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Find all bindings involving a specific entity.
     *
     * Note: This is a Phase 1 implementation that doesn't support complex queries.
     * For now, this method throws an exception indicating Phase 2 functionality is needed.
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        // Phase 1: Basic client doesn't support complex queries yet
        throw new \BadMethodCallException(
            'findByEntity requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
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
     */
    private function initializeSchema(): void
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
                ['name' => 'bindingId', 'dataType' => ['text']],
                ['name' => 'fromEntityType', 'dataType' => ['text']],
                ['name' => 'fromEntityId', 'dataType' => ['text']],
                ['name' => 'toEntityType', 'dataType' => ['text']],
                ['name' => 'toEntityId', 'dataType' => ['text']],
                ['name' => 'bindingType', 'dataType' => ['text']],
                ['name' => 'metadata', 'dataType' => ['text']], // Store as JSON string
                ['name' => 'createdAt', 'dataType' => ['date']],
                ['name' => 'updatedAt', 'dataType' => ['date']],
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
            if (is_string($id) && !empty($id)) {
                return $id;
            }
        }

        // Try id property
        if (property_exists($entity, 'id')) {
            $id = $entity->id;
            if (is_string($id) && !empty($id)) {
                return $id;
            }
        }

        throw new EntityExtractionException(
            'Cannot extract entity ID. Entity must implement EntityInterface, have getId() method, or id property.',
            $entity
        );
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
            if (is_string($type) && !empty($type)) {
                return $type;
            }
        }

        // Fallback to class name
        $className = get_class($entity);

        return basename(str_replace('\\', '/', $className));
    }

    /**
     * Validate and normalize metadata for storage.
     */
    public function validateAndNormalizeMetadata(array $metadata): array
    {
        // Check for invalid types
        $this->validateMetadataTypes($metadata);

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
     * Recursively validate metadata types.
     */
    private function validateMetadataTypes(array $metadata, string $path = ''): void
    {
        foreach ($metadata as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            if (is_resource($value)) {
                throw new InvalidMetadataException(
                    "Invalid metadata type 'resource' at path: {$currentPath}"
                );
            }

            if (is_object($value) && !($value instanceof \DateTimeInterface)) {
                throw new InvalidMetadataException(
                    "Invalid metadata type 'object' at path: {$currentPath}. Only DateTimeInterface objects are allowed."
                );
            }

            if (is_array($value)) {
                $this->validateMetadataTypes($value, $currentPath);
            }
        }
    }

    /**
     * Find bindings between two specific entities.
     *
     * Phase 1: Not supported yet - requires query functionality.
     */
    public function findBetweenEntities(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        ?string $bindingType = null
    ): array {
        throw new \BadMethodCallException(
            'findBetweenEntities requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Create a new query builder instance.
     *
     * Phase 1: Returns BasicWeaviateQueryBuilder with Phase 2 execution placeholders.
     */
    public function query(): QueryBuilderInterface
    {
        return new BasicWeaviateQueryBuilder($this->client, $this->collectionName);
    }

    /**
     * Execute a query and return matching bindings.
     *
     * Phase 1: Not supported yet - requires query functionality.
     */
    public function executeQuery(QueryBuilderInterface $query): array
    {
        throw new \BadMethodCallException(
            'executeQuery requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Count bindings matching a query.
     *
     * Phase 1: Not supported yet - requires query functionality.
     */
    public function count(QueryBuilderInterface $query): int
    {
        throw new \BadMethodCallException(
            'count requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }

    /**
     * Delete all bindings involving a specific entity.
     *
     * Phase 1: Not supported yet - requires query functionality to find bindings first.
     */
    public function deleteByEntity(string $entityType, string $entityId): int
    {
        throw new \BadMethodCallException(
            'deleteByEntity requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports query operations.'
        );
    }
}
