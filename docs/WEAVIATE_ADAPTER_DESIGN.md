# EdgeBinder Weaviate Adapter Design

## Overview

The Weaviate adapter for EdgeBinder leverages Weaviate's vector database capabilities to store and query entity relationships with rich metadata and semantic similarity features. This adapter is particularly powerful for AI/ML applications where relationships can be discovered through vector similarity.

## 🎯 **Implementation Strategy: Phased Approach**

### **Phase 1: Basic Adapter (Current Implementation)**
**Status: Ready to Implement**
- Uses current Zestic Weaviate PHP client capabilities
- Implements core `PersistenceAdapterInterface` methods
- Provides basic relationship storage and retrieval
- Supports rich metadata without vector features

### **Phase 2: Vector Enhancement (Future)**
**Status: Requires Client Enhancement**
- Contribute vector query support to Zestic client
- Add semantic similarity search capabilities
- Implement advanced GraphQL query features
- Enable AI/ML relationship discovery

This phased approach allows us to deliver a functional adapter immediately while building toward advanced vector capabilities.

## Core Design Principles

1. **Vector-First**: Relationships can have vector representations for semantic similarity
2. **Rich Metadata**: Full support for complex metadata structures
3. **Cross-References**: Leverage Weaviate's cross-reference capabilities for entity linking
4. **Semantic Queries**: Enable similarity-based relationship discovery
5. **Schema Flexibility**: Dynamic schema management for different binding types

## Weaviate Schema Design

### Core Classes

#### 1. Binding Class
```json
{
  "class": "EdgeBinding",
  "description": "Represents a relationship between two entities",
  "properties": [
    {
      "name": "bindingId",
      "dataType": ["string"],
      "description": "Unique identifier for the binding"
    },
    {
      "name": "fromEntityType",
      "dataType": ["string"],
      "description": "Type of the source entity"
    },
    {
      "name": "fromEntityId", 
      "dataType": ["string"],
      "description": "ID of the source entity"
    },
    {
      "name": "toEntityType",
      "dataType": ["string"],
      "description": "Type of the target entity"
    },
    {
      "name": "toEntityId",
      "dataType": ["string"], 
      "description": "ID of the target entity"
    },
    {
      "name": "bindingType",
      "dataType": ["string"],
      "description": "Type of relationship (e.g., 'has_access', 'belongs_to')"
    },
    {
      "name": "metadata",
      "dataType": ["object"],
      "description": "Rich metadata associated with the relationship"
    },
    {
      "name": "vectorProperties",
      "dataType": ["object"],
      "description": "Vector-specific metadata (similarities, distances, etc.)"
    },
    {
      "name": "createdAt",
      "dataType": ["date"],
      "description": "When the binding was created"
    },
    {
      "name": "updatedAt",
      "dataType": ["date"],
      "description": "When the binding was last updated"
    }
  ],
  "vectorizer": "text2vec-openai",
  "moduleConfig": {
    "text2vec-openai": {
      "vectorizeClassName": false,
      "vectorizePropertyName": false
    }
  }
}
```

#### 2. Entity Reference Class (Optional)
```json
{
  "class": "EntityReference",
  "description": "Represents an entity that can be bound to others",
  "properties": [
    {
      "name": "entityId",
      "dataType": ["string"]
    },
    {
      "name": "entityType", 
      "dataType": ["string"]
    },
    {
      "name": "entityData",
      "dataType": ["object"],
      "description": "Optional entity metadata for vector generation"
    }
  ],
  "vectorizer": "text2vec-openai"
}
```

## Adapter Implementation Structure

```
src/
├── WeaviateAdapter.php              # Main adapter implementation
├── Schema/
│   ├── SchemaManager.php           # Manages Weaviate schema
│   └── BindingSchema.php           # Binding class schema definition
├── Query/
│   ├── WeaviateQueryBuilder.php    # Weaviate-specific query builder
│   └── VectorQueryBuilder.php     # Vector similarity queries
├── Mapping/
│   ├── BindingMapper.php          # Maps EdgeBinder objects to Weaviate
│   └── MetadataMapper.php         # Handles metadata serialization
├── Vector/
│   ├── VectorGenerator.php        # Generates vectors from metadata
│   └── SimilarityCalculator.php   # Calculates relationship similarities
└── Exception/
    ├── WeaviateException.php      # Weaviate-specific exceptions
    └── SchemaException.php        # Schema-related exceptions
```

## Key Features

### 1. Vector-Enhanced Relationships
```php
// Store binding with vector representation
$binding = new Binding(
    id: 'binding-123',
    from: $workspace,
    to: $codeRepo,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'semantic_similarity' => 0.95,
        'embedding_model' => 'text-embedding-ada-002',
        'confidence_score' => 0.87
    ]
);

// Vector is generated from metadata and relationship context
$adapter->store($binding);
```

### 2. Semantic Similarity Queries
```php
// Find similar relationships based on vector similarity
$similarBindings = $adapter->findSimilarBindings(
    $referenceBinding,
    threshold: 0.8,
    limit: 10
);

// Find relationships by semantic concepts
$conceptualBindings = $adapter->findBySemanticConcepts(
    concepts: ['access control', 'permissions'],
    certainty: 0.7
);
```

### 3. Rich Metadata Queries
```php
// Complex metadata filtering with vector similarity
$results = $adapter->query()
    ->from($workspace)
    ->type('has_access')
    ->where('metadata.access_level', 'write')
    ->where('vectorProperties.confidence_score', '>', 0.8)
    ->nearVector($targetVector, 0.9)
    ->limit(20)
    ->get();
```

## Vector Generation Strategy

### Metadata-Based Vectors
```php
class MetadataVectorGenerator
{
    public function generateVector(BindingInterface $binding): array
    {
        $context = [
            'binding_type' => $binding->getType(),
            'from_entity' => $binding->getFromEntity()->getType(),
            'to_entity' => $binding->getToEntity()->getType(),
            'metadata_summary' => $this->summarizeMetadata($binding->getMetadata())
        ];
        
        return $this->vectorizer->vectorize(json_encode($context));
    }
}
```

### Relationship Context Vectors
- Combine entity types, binding type, and key metadata
- Generate embeddings that capture relationship semantics
- Enable discovery of similar relationship patterns

## Query Capabilities

### 1. Standard EdgeBinder Queries
```graphql
{
  Get {
    EdgeBinding(
      where: {
        path: ["bindingType"]
        operator: Equal
        valueString: "has_access"
      }
    ) {
      bindingId
      fromEntityId
      toEntityId
      metadata
    }
  }
}
```

### 2. Vector Similarity Queries
```graphql
{
  Get {
    EdgeBinding(
      nearVector: {
        vector: [0.1, 0.2, -0.3, ...]
        certainty: 0.8
      }
    ) {
      bindingId
      metadata
      _additional {
        certainty
        distance
      }
    }
  }
}
```

### 3. Hybrid Queries (Metadata + Vector)
```graphql
{
  Get {
    EdgeBinding(
      where: {
        operator: And
        operands: [
          {
            path: ["metadata", "access_level"]
            operator: Equal
            valueString: "write"
          }
        ]
      }
      nearText: {
        concepts: ["high priority", "critical access"]
        certainty: 0.7
      }
    ) {
      bindingId
      metadata
      vectorProperties
    }
  }
}
```

## Performance Optimizations

### 1. Batch Operations
```php
public function storeBatch(array $bindings): void
{
    $objects = array_map([$this->mapper, 'toWeaviateObject'], $bindings);
    $this->client->batch()->createObjects($objects);
}
```

### 2. Vector Caching
```php
class VectorCache
{
    public function getCachedVector(string $bindingId): ?array
    {
        return $this->cache->get("vector:{$bindingId}");
    }
    
    public function cacheVector(string $bindingId, array $vector): void
    {
        $this->cache->set("vector:{$bindingId}", $vector, 3600);
    }
}
```

### 3. Index Optimization
- Create indexes on frequently queried metadata fields
- Optimize vector index settings for relationship patterns
- Use Weaviate's HNSW index tuning for performance

## Error Handling

### Connection Management
```php
class WeaviateConnectionManager
{
    public function executeWithRetry(callable $operation, int $maxRetries = 3)
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                return $operation();
            } catch (WeaviateConnectionException $e) {
                if ($i === $maxRetries - 1) throw $e;
                $this->reconnect();
            }
        }
    }
}
```

## Configuration

### Adapter Configuration
```php
$config = [
    'host' => 'http://localhost:8080',
    'api_key' => 'your-api-key',
    'schema' => [
        'auto_create' => true,
        'binding_class' => 'EdgeBinding',
        'entity_class' => 'EntityReference'
    ],
    'vectorizer' => [
        'provider' => 'openai',
        'model' => 'text-embedding-ada-002',
        'api_key' => 'openai-api-key'
    ],
    'performance' => [
        'batch_size' => 100,
        'vector_cache_ttl' => 3600,
        'connection_timeout' => 30
    ]
];
```

## Implementation Roadmap

### 🚀 **Phase 1: Basic Adapter (Immediate - 2-3 weeks)**
**Goal**: Functional EdgeBinder adapter with core relationship management

#### **What We Can Build Now:**
- ✅ **Core CRUD Operations**: Full `PersistenceAdapterInterface` implementation
- ✅ **Rich Metadata Storage**: Complex relationship metadata support
- ✅ **Basic Querying**: Entity-based relationship queries
- ✅ **Multi-Tenancy**: Tenant-isolated relationship data
- ✅ **Schema Management**: Automatic collection creation and management

#### **Current Client Capabilities Used:**
```php
// ✅ Available in Zestic client now
$collection->data()->create($properties, $id);
$collection->data()->get($id);
$collection->data()->update($id, $properties);
$collection->data()->delete($id);
$collection->query()->where('field', 'value')->get();
```

#### **Phase 1 Deliverables:**
1. **WeaviateAdapter** class implementing `PersistenceAdapterInterface`
2. **Basic relationship storage** with metadata
3. **Entity-based queries** (find all relationships for an entity)
4. **Comprehensive tests** and documentation
5. **Usage examples** and best practices
6. **Composer package** ready for use

### 🎯 **Phase 2: Vector Enhancement (Future - 4-6 weeks)**
**Goal**: Advanced semantic relationship discovery and vector queries

#### **What Requires Client Enhancement:**
- ❌ **Vector Similarity Search**: `nearVector()`, `nearText()` queries
- ❌ **GraphQL Support**: Advanced query capabilities
- ❌ **Semantic Concept Search**: Natural language relationship queries
- ❌ **Batch Vector Operations**: Efficient bulk similarity queries
- ❌ **Vector Index Configuration**: HNSW settings, distance metrics

#### **Client Contributions Needed:**
```php
// ❌ Not yet available - needs client enhancement
$collection->query()->nearVector($vector, 0.8)->get();
$collection->query()->nearText(['concepts'], 0.7)->get();
$client->graphql()->get('Collection')->nearVector($vector)->do();
```

#### **Phase 2 Deliverables:**
1. **Enhanced Zestic Client**: Vector query support
2. **Semantic Search Methods**: `findSimilarBindings()`, `findBySemanticConcepts()`
3. **Vector-Enhanced Queries**: Hybrid metadata + vector filtering
4. **AI/ML Integration**: Automatic relationship discovery
5. **Performance Optimizations**: Batch operations and caching

### 📋 **Implementation Priority**

#### **High Priority (Phase 1)**
- Core adapter implementation
- Basic relationship management
- Metadata storage and retrieval
- Entity-based queries

#### **Medium Priority (Phase 2)**
- Vector similarity search
- Semantic concept queries
- GraphQL integration
- Advanced query capabilities

#### **Low Priority (Future)**
- Performance optimizations
- Advanced vector configurations
- Machine learning integrations
- Analytics and reporting

## Next Steps

### **Immediate Actions (Phase 1)**
1. **Implement Basic Adapter**: Build core functionality with current client
2. **Create Test Suite**: Comprehensive testing against real Weaviate
3. **Write Documentation**: Usage examples and API reference
4. **Package Release**: Publish to Packagist for community use

### **Future Actions (Phase 2)**
1. **Assess Client Gaps**: Identify specific vector features needed
2. **Contribute to Client**: Add vector query support to Zestic client
3. **Enhance Adapter**: Implement advanced semantic features
4. **Community Feedback**: Gather input on vector capabilities

This phased approach ensures we deliver immediate value while building toward advanced vector database capabilities that leverage Weaviate's full potential for semantic relationship discovery.
