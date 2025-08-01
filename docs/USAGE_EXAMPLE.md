# EdgeBinder Weaviate Adapter Usage Examples

## Installation

```bash
composer require edgebinder/weaviate-adapter
```

## Setup Options

### Option 1: Using the Registry System (Recommended)

```php
<?php

use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;
use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

// Register the Weaviate adapter factory
AdapterRegistry::register(new WeaviateAdapterFactory());

// Configure your container to provide the Weaviate client
// This example assumes you have a PSR-11 container
$container = /* your PSR-11 container */;

// Create EdgeBinder using configuration
$config = [
    'adapter' => 'weaviate',
    'weaviate_client' => 'weaviate.client.default',
    'collection_name' => 'MyAppBindings',
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai'
    ]
];

$binder = EdgeBinder::fromConfiguration($config, $container);
```

### Option 2: Direct Adapter Creation

```php
<?php

use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

// Connect to local Weaviate instance
$weaviateClient = WeaviateClient::connectToLocal();

// Or connect to Weaviate Cloud
$weaviateClient = WeaviateClient::connectToWeaviateCloud(
    'my-cluster.weaviate.network',
    new ApiKey('your-wcd-api-key')
);

// Create the EdgeBinder adapter
$adapter = new WeaviateAdapter($weaviateClient, [
    'collection_name' => 'MyAppBindings',
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai'
    ]
]);

// Create EdgeBinder instance
$binder = EdgeBinder::fromAdapter($adapter);
```

## Basic Operations

```php
<?php

use EdgeBinder\Entity;

// Create entities
$workspace = new Entity('workspace-123', 'Workspace');
$project = new Entity('project-456', 'Project');

// Create a binding with rich metadata
$binding = $binder->bind(
    from: $workspace,
    to: $project,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => 'user-789',
        'granted_at' => new DateTimeImmutable(),
        'expires_at' => null,
        'confidence_score' => 0.95,
        'semantic_similarity' => 0.87,
        'tags' => ['production', 'critical']
    ]
);

echo "Created binding: " . $binding->getId();

// Find the binding
$foundBinding = $binder->find($binding->getId());
if ($foundBinding) {
    echo "Found binding with metadata: " . json_encode($foundBinding->getMetadata());
}

// Update metadata
$binder->updateMetadata($binding->getId(), [
    'access_level' => 'read',
    'modified_by' => 'admin-001',
    'modified_at' => new DateTimeImmutable()
]);

// Query bindings
$bindings = $binder->query()
    ->from($workspace)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();

echo "Found " . count($bindings) . " write access bindings";
```

## Vector Similarity Queries

```php
<?php

// Find similar bindings based on vector similarity
$similarBindings = $adapter->findSimilarBindings(
    $referenceBinding,
    threshold: 0.8,
    limit: 10
);

foreach ($similarBindings as $similar) {
    echo "Similar binding: " . $similar->getId() . "\n";
    echo "Similarity score: " . $similar->getMetadata()['similarity_score'] ?? 'N/A' . "\n";
}

// Find bindings by semantic concepts
$conceptualBindings = $adapter->findBySemanticConcepts(
    concepts: ['access control', 'permissions', 'security'],
    certainty: 0.7
);

echo "Found " . count($conceptualBindings) . " conceptually related bindings";
```

## Advanced Weaviate Features

```php
<?php

// Use Weaviate-specific query builder for complex queries
$results = $adapter->query()
    ->from($workspace)
    ->type('has_access')
    ->where('metadata.access_level', 'write')
    ->nearText(['high priority', 'critical access'], 0.8)
    ->limit(20)
    ->get();

// Vector-based queries
$targetVector = [0.1, 0.2, -0.3, /* ... 1536 dimensions */];
$vectorResults = $adapter->query()
    ->type('has_access')
    ->nearVector($targetVector, 0.9)
    ->where('metadata.confidence_score', '>', 0.8)
    ->get();
```

## Multi-Tenant Usage

```php
<?php

// Configure adapter for multi-tenancy
$adapter = new WeaviateAdapter($weaviateClient, [
    'collection_name' => 'TenantBindings',
    'multi_tenancy' => [
        'enabled' => true,
        'default_tenant' => 'default'
    ]
]);

// Create tenant-specific bindings
$tenantAdapter = $adapter->withTenant('customer-123');
$tenantBinder = new EdgeBinder($tenantAdapter);

$binding = $tenantBinder->bind(
    from: $workspace,
    to: $project,
    type: 'has_access',
    metadata: ['tenant_id' => 'customer-123']
);

// Query tenant-specific data
$tenantBindings = $tenantBinder->query()
    ->from($workspace)
    ->get();
```

## Configuration Options

```php
<?php

$config = [
    // Collection settings
    'collection_name' => 'EdgeBindings',
    
    // Schema configuration
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai',
        'vector_index_config' => [
            'distance' => 'cosine',
            'ef_construction' => 128,
            'max_connections' => 64
        ]
    ],
    
    // Vectorizer settings
    'vectorizer' => [
        'provider' => 'openai',
        'model' => 'text-embedding-ada-002',
        'api_key' => 'your-openai-key'
    ],
    
    // Performance tuning
    'performance' => [
        'batch_size' => 100,
        'vector_cache_ttl' => 3600,
        'connection_timeout' => 30,
        'retry_attempts' => 3
    ],
    
    // Multi-tenancy
    'multi_tenancy' => [
        'enabled' => false,
        'default_tenant' => 'default'
    ]
];

$adapter = new WeaviateAdapter($weaviateClient, $config);
```

## Framework Integration Examples

### Laminas/Mezzio Integration

```php
<?php

// In your Module.php
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

class Module
{
    public function onBootstrap($e)
    {
        // Register the Weaviate adapter factory
        AdapterRegistry::register(new WeaviateAdapterFactory());
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                'weaviate.client.rag' => function($container) {
                    $config = $container->get('config')['weaviate'];
                    return WeaviateClient::connectToLocal();
                },
                EdgeBinder::class => function($container) {
                    $config = $container->get('config')['edgebinder']['rag'];
                    return EdgeBinder::fromConfiguration($config, $container);
                },
            ],
        ];
    }
}

// In your config/autoload/edgebinder.global.php
return [
    'edgebinder' => [
        'rag' => [
            'adapter' => 'weaviate',
            'weaviate_client' => 'weaviate.client.rag',
            'collection_name' => 'RAGBindings',
            'schema' => ['auto_create' => true],
        ],
    ],
];
```

### Symfony Integration

```php
<?php

// In your bundle's boot method
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

class MyBundle extends Bundle
{
    public function boot()
    {
        AdapterRegistry::register(new WeaviateAdapterFactory());
    }
}

// In your services.yaml
services:
    weaviate.client.rag:
        class: Weaviate\WeaviateClient
        factory: ['Weaviate\WeaviateClient', 'connectToLocal']

    EdgeBinder\EdgeBinder:
        factory: ['EdgeBinder\EdgeBinder', 'fromConfiguration']
        arguments:
            - '%edgebinder.rag%'
            - '@service_container'

# In your config/packages/edgebinder.yaml
parameters:
    edgebinder.rag:
        adapter: 'weaviate'
        weaviate_client: 'weaviate.client.rag'
        collection_name: 'RAGBindings'
        schema:
            auto_create: true
```

### Laravel Integration

```php
<?php

// In your AppServiceProvider
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\EdgeBinder;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        AdapterRegistry::register(new WeaviateAdapterFactory());
    }

    public function register()
    {
        $this->app->singleton('weaviate.client.rag', function () {
            return WeaviateClient::connectToLocal();
        });

        $this->app->singleton(EdgeBinder::class, function ($app) {
            $config = config('edgebinder.rag');
            return EdgeBinder::fromConfiguration($config, $app);
        });
    }
}

// In your config/edgebinder.php
return [
    'rag' => [
        'adapter' => 'weaviate',
        'weaviate_client' => 'weaviate.client.rag',
        'collection_name' => 'RAGBindings',
        'schema' => [
            'auto_create' => true,
        ],
    ],
];
```

## Error Handling

```php
<?php

use EdgeBinder\Weaviate\Exception\WeaviateException;
use EdgeBinder\Weaviate\Exception\SchemaException;

try {
    $binding = $binder->bind($workspace, $project, 'has_access');
} catch (WeaviateException $e) {
    echo "Weaviate error: " . $e->getMessage();
    
    // Check if it's a connection issue
    if ($e->isRetryable()) {
        // Implement retry logic
        sleep(1);
        $binding = $binder->bind($workspace, $project, 'has_access');
    }
} catch (SchemaException $e) {
    echo "Schema error: " . $e->getMessage();
    // Handle schema-related issues
}
```

## Performance Optimization

```php
<?php

// Batch operations for better performance
$bindings = [
    ['from' => $workspace1, 'to' => $project1, 'type' => 'has_access'],
    ['from' => $workspace2, 'to' => $project2, 'type' => 'has_access'],
    // ... more bindings
];

$adapter->storeBatch($bindings);

// Use vector caching for repeated similarity queries
$adapter->enableVectorCache(ttl: 3600); // Cache for 1 hour

// Optimize queries with specific fields
$results = $adapter->query()
    ->select(['bindingId', 'bindingType', 'metadata.access_level'])
    ->from($workspace)
    ->limit(100)
    ->get();
```

## Integration with AI/ML Workflows

```php
<?php

// Automatically generate relationship vectors from metadata
$binding = $binder->bind(
    from: $document1,
    to: $document2,
    type: 'semantically_related',
    metadata: [
        'similarity_score' => 0.94,
        'topics' => ['machine learning', 'php', 'databases'],
        'content_summary' => 'Both documents discuss PHP database optimization techniques',
        'discovery_method' => 'cosine_similarity',
        'embedding_model' => 'text-embedding-ada-002'
    ]
);

// Query for AI-discovered relationships
$relatedDocs = $binder->query()
    ->from($currentDocument)
    ->type('semantically_related')
    ->where('similarity_score', '>', 0.9)
    ->where('discovery_method', 'cosine_similarity')
    ->orderBy('similarity_score', 'desc')
    ->limit(5)
    ->get();

// Find documents with similar topics using vector search
$topicSimilar = $adapter->findBySemanticConcepts(
    concepts: ['machine learning', 'artificial intelligence'],
    certainty: 0.8
);
```

This adapter provides powerful vector database capabilities while maintaining full compatibility with the EdgeBinder interface, enabling semantic relationship discovery and rich metadata queries.
