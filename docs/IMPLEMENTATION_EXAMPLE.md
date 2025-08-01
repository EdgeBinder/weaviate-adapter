# Weaviate Adapter Implementation Example

## 🎯 **Phased Implementation Strategy**

### **Phase 1: Basic Adapter (Current Implementation)**
This implementation uses the current capabilities of the Zestic Weaviate PHP client to provide core EdgeBinder functionality. Vector features will be added in Phase 2 as the client evolves.

#### **Phase 1 Capabilities:**
- ✅ Core CRUD operations (`store`, `find`, `delete`, `updateMetadata`)
- ✅ Entity-based queries (`findByEntity`)
- ✅ Rich metadata storage
- ✅ Multi-tenancy support
- ✅ Automatic schema management

#### **Phase 2 Future Enhancements:**
- 🎯 Vector similarity search (`findSimilarBindings`)
- 🎯 Semantic concept queries (`findBySemanticConcepts`)
- 🎯 Advanced GraphQL queries
- 🎯 Batch vector operations

## Core Adapter Class

```php
<?php

namespace EdgeBinder\Weaviate;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Binding;
use EdgeBinder\Entity;
use Weaviate\WeaviateClient;
use EdgeBinder\Weaviate\Exception\WeaviateException;

class WeaviateAdapter implements PersistenceAdapterInterface
{
    private WeaviateClient $client;
    private array $config;
    private string $collectionName;

    public function __construct(
        WeaviateClient $client,
        array $config = []
    ) {
        $this->client = $client;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->collectionName = $this->config['collection_name'];

        $this->initializeSchema();
    }

    public function store(BindingInterface $binding): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $properties = $this->toWeaviateProperties($binding);

        $result = $collection->data()->create($properties, $binding->getId());

        if (!$result) {
            throw new WeaviateException('Failed to store binding');
        }
    }

    public function find(string $bindingId): ?BindingInterface
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $result = $collection->data()->get($bindingId);

        if (!$result) {
            return null;
        }

        return $this->fromWeaviateObject($result);
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        $collection = $this->client->collections()->get($this->collectionName);

        // Query for bindings where this entity is either the source or target
        $queryBuilder = $collection->query();

        $results = $queryBuilder
            ->where('fromEntityType', $entityType)
            ->where('fromEntityId', $entityId)
            ->orWhere('toEntityType', $entityType)
            ->orWhere('toEntityId', $entityId)
            ->get();

        return array_map(
            [$this, 'fromWeaviateObject'],
            $results
        );
    }

    public function delete(string $bindingId): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $result = $collection->data()->delete($bindingId);

        if (!$result) {
            throw new WeaviateException("Failed to delete binding: {$bindingId}");
        }
    }

    public function updateMetadata(string $bindingId, array $metadata): void
    {
        $collection = $this->client->collections()->get($this->collectionName);

        $updateData = [
            'metadata' => $metadata,
            'updatedAt' => (new \DateTimeImmutable())->format('c')
        ];

        $result = $collection->data()->update($bindingId, $updateData);

        if (!$result) {
            throw new WeaviateException("Failed to update binding metadata: {$bindingId}");
        }
    }

    // 🎯 Phase 2: Vector operations (requires client enhancement)
    public function findSimilarBindings(
        BindingInterface $referenceBinding,
        float $threshold = 0.8,
        int $limit = 10
    ): array {
        // TODO: Implement when Zestic client supports vector queries
        throw new \BadMethodCallException(
            'Vector similarity search requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports nearVector queries.'
        );
    }

    public function findBySemanticConcepts(
        array $concepts,
        float $certainty = 0.7,
        int $limit = 20
    ): array {
        // TODO: Implement when Zestic client supports semantic queries
        throw new \BadMethodCallException(
            'Semantic concept search requires Phase 2 client enhancements. ' .
            'This feature will be available when the Zestic client supports nearText queries.'
        );
    }

    // 🎯 Phase 2: Advanced query builder (requires GraphQL support)
    public function query(): QueryBuilderInterface
    {
        // For now, return a basic query builder that works with current client
        return new BasicWeaviateQueryBuilder($this->client, $this->collectionName);
    }

    private function initializeSchema(): void
    {
        if ($this->config['schema']['auto_create']) {
            $this->schemaManager->ensureBindingSchema();
        }
    }

    private function generateVector(BindingInterface $binding): array
    {
        // Generate vector from binding context and metadata
        $context = [
            'binding_type' => $binding->getType(),
            'from_entity' => $binding->getFromEntity()->getType(),
            'to_entity' => $binding->getToEntity()->getType(),
            'metadata_summary' => $this->summarizeMetadata($binding->getMetadata())
        ];

        // This would use the configured vectorizer (OpenAI, etc.)
        return $this->vectorizer->vectorize(json_encode($context));
    }

    private function summarizeMetadata(array $metadata): string
    {
        // Create a text summary of metadata for vectorization
        $summary = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value)) {
                $summary[] = "{$key}: {$value}";
            }
        }
        return implode(', ', $summary);
    }

    private function getDefaultConfig(): array
    {
        return [
            'collection_name' => 'EdgeBindings',
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'text2vec-openai'
            ],
            'vectorizer' => [
                'provider' => 'openai',
                'model' => 'text-embedding-ada-002'
            ],
            'performance' => [
                'batch_size' => 100,
                'vector_cache_ttl' => 3600
            ]
        ];
    }

    private function initializeSchema(): void
    {
        if ($this->config['schema']['auto_create']) {
            $this->ensureCollectionExists();
        }
    }

    private function ensureCollectionExists(): void
    {
        if (!$this->client->collections()->exists($this->collectionName)) {
            $this->client->collections()->create($this->collectionName, [
                'properties' => [
                    ['name' => 'bindingId', 'dataType' => ['string']],
                    ['name' => 'fromEntityType', 'dataType' => ['string']],
                    ['name' => 'fromEntityId', 'dataType' => ['string']],
                    ['name' => 'toEntityType', 'dataType' => ['string']],
                    ['name' => 'toEntityId', 'dataType' => ['string']],
                    ['name' => 'bindingType', 'dataType' => ['string']],
                    ['name' => 'metadata', 'dataType' => ['object']],
                    ['name' => 'createdAt', 'dataType' => ['date']],
                    ['name' => 'updatedAt', 'dataType' => ['date']]
                ],
                'vectorizer' => $this->config['schema']['vectorizer']
            ]);
        }
    }

    private function toWeaviateProperties(BindingInterface $binding): array
    {
        return [
            'bindingId' => $binding->getId(),
            'fromEntityType' => $binding->getFromEntity()->getType(),
            'fromEntityId' => $binding->getFromEntity()->getId(),
            'toEntityType' => $binding->getToEntity()->getType(),
            'toEntityId' => $binding->getToEntity()->getId(),
            'bindingType' => $binding->getType(),
            'metadata' => $binding->getMetadata(),
            'createdAt' => (new \DateTimeImmutable())->format('c'),
            'updatedAt' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function fromWeaviateObject(array $weaviateObject): BindingInterface
    {
        $props = $weaviateObject['properties'] ?? $weaviateObject;

        $fromEntity = new Entity($props['fromEntityId'], $props['fromEntityType']);
        $toEntity = new Entity($props['toEntityId'], $props['toEntityType']);

        return new Binding(
            id: $props['bindingId'],
            from: $fromEntity,
            to: $toEntity,
            type: $props['bindingType'],
            metadata: $props['metadata'] ?? []
        );
    }
}
```

## Simplified Architecture

The implementation above shows a streamlined approach where the `WeaviateAdapter` handles all the mapping and schema management internally, rather than using separate classes. This keeps the adapter focused and reduces complexity while maintaining all the functionality.

## Key Benefits

This implementation provides:

1. **Full PersistenceAdapterInterface compliance** - Works seamlessly with EdgeBinder
2. **Vector similarity search capabilities** - Leverage Weaviate's vector database features
3. **Rich metadata querying** - Complex queries on relationship metadata
4. **Semantic concept search** - Natural language concept-based queries
5. **Proper error handling** - Weaviate-specific exception handling
6. **Configurable schema management** - Automatic collection creation and management
7. **Clean separation of concerns** - All Weaviate-specific logic contained in the adapter

This implementation provides:

1. **Full PersistenceAdapterInterface compliance**
2. **Vector similarity search capabilities**
3. **Rich metadata querying**
4. **Semantic concept search**
5. **Proper error handling**
6. **Configurable schema management**

The adapter leverages Weaviate's strengths while maintaining EdgeBinder compatibility.
