# EdgeBinder Weaviate Adapter API Reference

This document provides a comprehensive API reference for the EdgeBinder Weaviate Adapter.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [API Methods](#api-methods)
- [Error Handling](#error-handling)
- [Examples](#examples)

## Installation

```bash
composer require edgebinder/weaviate-adapter
```

## Configuration

### Basic Configuration

```php
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use Weaviate\WeaviateClient;

$client = WeaviateClient::connectToLocal();
$adapter = new WeaviateAdapter($client);
```

### Advanced Configuration

```php
$adapter = new WeaviateAdapter($client, [
    'collection_name' => 'MyBindings',
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai'
    ],
    'vectorizer' => [
        'provider' => 'openai',
        'model' => 'text-embedding-ada-002',
        'api_key' => 'your-openai-key'
    ],
    'performance' => [
        'batch_size' => 100,
        'vector_cache_ttl' => 3600
    ]
]);
```

## Basic Usage

```php
use EdgeBinder\EdgeBinder;
use EdgeBinder\Entity;

// Create EdgeBinder instance
$binder = new EdgeBinder($adapter);

// Create entities
$workspace = new Entity('workspace-123', 'Workspace');
$project = new Entity('project-456', 'Project');

// Create binding
$binding = $binder->bind(
    from: $workspace,
    to: $project,
    type: 'has_access',
    metadata: ['access_level' => 'write']
);
```

## API Methods

### Core Methods (Phase 1)

#### `store(BindingInterface $binding): void`

Stores a binding in Weaviate.

```php
$adapter->store($binding);
```

#### `find(string $bindingId): ?BindingInterface`

Retrieves a binding by ID.

```php
$binding = $adapter->find('binding-123');
```

#### `delete(string $bindingId): void`

Deletes a binding by ID.

```php
$adapter->delete('binding-123');
```

#### `updateMetadata(string $bindingId, array $metadata): void`

Updates binding metadata.

```php
$adapter->updateMetadata('binding-123', [
    'access_level' => 'read',
    'updated_by' => 'admin'
]);
```

#### `findByEntity(string $entityType, string $entityId): array`

Finds all bindings for a specific entity.

```php
$bindings = $adapter->findByEntity('Workspace', 'workspace-123');
```

### Vector Methods (Phase 2 - Future)

#### `findSimilarBindings(BindingInterface $reference, float $threshold, int $limit): array`

Finds bindings similar to a reference binding using vector similarity.

```php
$similar = $adapter->findSimilarBindings($binding, 0.8, 10);
```

#### `findBySemanticConcepts(array $concepts, float $certainty, int $limit): array`

Finds bindings by semantic concepts.

```php
$conceptual = $adapter->findBySemanticConcepts(
    ['access control', 'permissions'],
    0.7,
    20
);
```

## Error Handling

The adapter throws specific exceptions for different error conditions:

### WeaviateException

Base exception for Weaviate-related errors.

```php
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;

try {
    $adapter->store($binding);
} catch (WeaviateException $e) {
    echo "Weaviate error: " . $e->getMessage();
    
    if ($e->isRetryable()) {
        // Implement retry logic
    }
}
```

### SchemaException

Exception for schema-related errors.

```php
use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;

try {
    $adapter->initializeSchema();
} catch (SchemaException $e) {
    echo "Schema error: " . $e->getMessage();
}
```

## Examples

### Multi-Tenancy

```php
// Configure for multi-tenancy
$adapter = new WeaviateAdapter($client, [
    'collection_name' => 'TenantBindings',
    'multi_tenancy' => [
        'enabled' => true,
        'default_tenant' => 'default'
    ]
]);

// Use with specific tenant
$tenantAdapter = $adapter->withTenant('customer-123');
$tenantBinder = new EdgeBinder($tenantAdapter);
```

### Batch Operations

```php
// Store multiple bindings efficiently
$bindings = [
    $binding1,
    $binding2,
    $binding3
];

$adapter->storeBatch($bindings);
```

### Complex Queries

```php
// Query with multiple conditions
$results = $adapter->query()
    ->from($workspace)
    ->type('has_access')
    ->where('metadata.access_level', 'write')
    ->where('metadata.confidence_score', '>', 0.8)
    ->limit(50)
    ->get();
```

### Vector Operations (Phase 2)

```php
// Find similar relationships
$similar = $adapter->findSimilarBindings($referenceBinding, 0.8, 10);

// Semantic search
$conceptual = $adapter->findBySemanticConcepts(
    ['machine learning', 'artificial intelligence'],
    0.7
);

// Hybrid queries (metadata + vector)
$results = $adapter->query()
    ->type('has_access')
    ->where('metadata.access_level', 'write')
    ->nearText(['high priority', 'critical'], 0.8)
    ->limit(20)
    ->get();
```

## Configuration Options

### Schema Configuration

```php
'schema' => [
    'auto_create' => true,              // Automatically create collections
    'vectorizer' => 'text2vec-openai',  // Vectorizer module
    'vector_index_config' => [
        'distance' => 'cosine',         // Distance metric
        'ef_construction' => 128,       // HNSW parameter
        'max_connections' => 64         // HNSW parameter
    ]
]
```

### Performance Configuration

```php
'performance' => [
    'batch_size' => 100,           // Batch operation size
    'vector_cache_ttl' => 3600,    // Vector cache TTL in seconds
    'connection_timeout' => 30,    // Connection timeout
    'retry_attempts' => 3          // Retry attempts for failed operations
]
```

### Multi-Tenancy Configuration

```php
'multi_tenancy' => [
    'enabled' => true,           // Enable multi-tenancy
    'default_tenant' => 'default' // Default tenant name
]
```

This API reference will be expanded as the implementation progresses through Phase 1 and Phase 2.
