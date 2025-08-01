# EdgeBinder Weaviate Adapter

[![Tests](https://github.com/edgebinder/weaviate-adapter/actions/workflows/test.yml/badge.svg)](https://github.com/edgebinder/weaviate-adapter/actions/workflows/test.yml)
[![Lint](https://github.com/edgebinder/weaviate-adapter/actions/workflows/lint.yml/badge.svg)](https://github.com/edgebinder/weaviate-adapter/actions/workflows/lint.yml)
[![codecov](https://codecov.io/gh/edgebinder/weaviate-adapter/branch/main/graph/badge.svg)](https://codecov.io/gh/edgebinder/weaviate-adapter)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)](LICENSE)

A Weaviate adapter for EdgeBinder that leverages Weaviate's vector database capabilities to store and query entity relationships with rich metadata and semantic similarity features.

## 🎯 Implementation Strategy: Phased Approach

### Phase 1: Basic Adapter (Current Implementation)
**Status: Ready to Implement**
- Uses current Zestic Weaviate PHP client capabilities
- Implements core `PersistenceAdapterInterface` methods
- Provides basic relationship storage and retrieval
- Supports rich metadata without vector features

### Phase 2: Vector Enhancement (Future)
**Status: Requires Client Enhancement**
- Contribute vector query support to Zestic client
- Add semantic similarity search capabilities
- Implement advanced GraphQL query features
- Enable AI/ML relationship discovery

## Requirements

- PHP 8.3 or higher
- Composer
- Weaviate 1.31+ (for integration testing)
- Docker and Docker Compose (for local development)

## Installation

```bash
composer require edgebinder/weaviate-adapter
```

## Quick Start

### Option 1: Using the Registry System (Recommended)

```php
<?php

use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;
use Weaviate\WeaviateClient;

// Register the Weaviate adapter factory
AdapterRegistry::register(new WeaviateAdapterFactory());

// Configure your container to provide the Weaviate client
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

// Use EdgeBinder as normal
$workspace = new Entity('workspace-123', 'Workspace');
$project = new Entity('project-456', 'Project');

$binding = $binder->bind(
    from: $workspace,
    to: $project,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => 'user-789',
        'confidence_score' => 0.95
    ]
);
```

### Option 2: Direct Adapter Creation

```php
<?php

use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use Weaviate\WeaviateClient;

// Connect to Weaviate
$weaviateClient = WeaviateClient::connectToLocal();

// Create the adapter directly
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

## Framework Integration

The Weaviate adapter supports the EdgeBinder registry system, making it easy to integrate with any PHP framework:

### Laminas/Mezzio

```php
// In your Module.php or application bootstrap
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

AdapterRegistry::register(new WeaviateAdapterFactory());

// In your service factory
public function __invoke(ContainerInterface $container): EdgeBinder
{
    $config = $container->get('config')['edgebinder']['rag'];
    return EdgeBinder::fromConfiguration($config, $container);
}
```

### Symfony

```php
// In your bundle boot method or compiler pass
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

AdapterRegistry::register(new WeaviateAdapterFactory());

// In your service
public function createEdgeBinder(ContainerInterface $container): EdgeBinder
{
    $config = $container->getParameter('edgebinder.rag');
    return EdgeBinder::fromConfiguration($config, $container);
}
```

### Laravel

```php
// In your service provider boot method
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

public function boot()
{
    AdapterRegistry::register(new WeaviateAdapterFactory());
}

// In your service
public function createEdgeBinder(): EdgeBinder
{
    $config = config('edgebinder.rag');
    return EdgeBinder::fromConfiguration($config, app());
}
```

### Configuration Example

```php
return [
    'edgebinder' => [
        'rag' => [
            'adapter' => 'weaviate',
            'weaviate_client' => 'weaviate.client.rag',
            'collection_name' => 'RAGBindings',
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
        ],
    ],
];
```

## Development

### Setup

```bash
# Clone the repository
git clone https://github.com/edgebinder/weaviate-adapter.git
cd weaviate-adapter

# Install dependencies
composer install
```

### Running Tests

#### Unit Tests (No External Dependencies)
```bash
# Run unit tests only
composer test-unit
```

#### Integration Tests (Requires Weaviate)
```bash
# Using Docker (Recommended)
composer test-docker

# Using external Weaviate instance
export WEAVIATE_URL=http://localhost:8080
composer test-integration
```

#### All Tests
```bash
# Run all tests with Docker
composer test-docker

# Run all tests with external Weaviate
composer test
```

### Code Quality

```bash
# Run PHPStan static analysis
composer phpstan

# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix

# Run all linting
composer lint
```

### Docker Development

```bash
# Start Weaviate for development
composer docker-start

# Stop Weaviate
composer docker-stop

# Reset Weaviate data
composer docker-reset
```

## Features

### Phase 1 (Current)
- ✅ Core CRUD operations
- ✅ Rich metadata storage
- ✅ Entity-based queries
- ✅ Multi-tenancy support
- ✅ Automatic schema management

### Phase 2 (Planned)
- 🎯 Vector similarity search
- 🎯 Semantic concept queries
- 🎯 Advanced GraphQL queries
- 🎯 Batch vector operations
- 🎯 AI/ML integration features

## Architecture

```
src/
├── WeaviateAdapter.php          # Main adapter implementation
├── Exception/
│   ├── WeaviateException.php    # Weaviate-specific exceptions
│   └── SchemaException.php      # Schema-related exceptions
├── Query/
│   └── BasicQueryBuilder.php    # Basic query capabilities
├── Schema/
│   └── SchemaManager.php        # Schema management
├── Mapping/
│   └── BindingMapper.php        # Object mapping
└── Vector/
    └── VectorGenerator.php      # Vector generation (Phase 2)
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass (`composer test-docker`)
5. Ensure code quality passes (`composer lint`)
6. Submit a pull request

## License

This project is licensed under the Apache License 2.0. See the [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/edgebinder/weaviate-adapter/issues).
