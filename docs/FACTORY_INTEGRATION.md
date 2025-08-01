# Weaviate Adapter Factory Integration

This document explains how to use the WeaviateAdapterFactory with the EdgeBinder registry system for framework-agnostic integration.

## Overview

The WeaviateAdapterFactory implements the EdgeBinder extensible adapter system, allowing the Weaviate adapter to be created consistently across all PHP frameworks using configuration-driven instantiation.

## Key Benefits

- **Framework Agnostic**: Works identically in Laminas, Symfony, Laravel, Slim, and any PSR-11 framework
- **Configuration-Driven**: Create adapters from configuration arrays instead of manual instantiation
- **Container Integration**: Leverages PSR-11 dependency injection containers
- **Consistent API**: Same configuration structure across all frameworks
- **Error Handling**: Comprehensive validation and helpful error messages

## Basic Usage

### 1. Register the Factory

```php
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

// Register the factory (do this once during application bootstrap)
AdapterRegistry::register(new WeaviateAdapterFactory());
```

### 2. Create EdgeBinder from Configuration

```php
use EdgeBinder\EdgeBinder;

$config = [
    'adapter' => 'weaviate',
    'weaviate_client' => 'weaviate.client.rag',
    'collection_name' => 'RAGBindings',
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai',
    ],
];

$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

## Configuration Structure

The factory expects configuration in this format:

```php
[
    'adapter' => 'weaviate',                    // Required: adapter type
    'weaviate_client' => 'service.name',       // Required: container service name
    'collection_name' => 'MyBindings',         // Optional: collection name
    'schema' => [                              // Optional: schema configuration
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai',
    ],
    'vectorizer' => [                          // Optional: vectorizer configuration
        'provider' => 'openai',
        'model' => 'text-embedding-ada-002',
    ],
    'performance' => [                         // Optional: performance tuning
        'batch_size' => 100,
        'vector_cache_ttl' => 3600,
    ],
]
```

## Framework Integration Patterns

### Laminas/Mezzio

```php
// In Module.php
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

class Module
{
    public function onBootstrap($e)
    {
        AdapterRegistry::register(new WeaviateAdapterFactory());
    }
    
    public function getServiceConfig()
    {
        return [
            'factories' => [
                'weaviate.client.rag' => WeaviateClientFactory::class,
                EdgeBinder::class => function($container) {
                    $config = $container->get('config')['edgebinder']['rag'];
                    return EdgeBinder::fromConfiguration($config, $container);
                },
            ],
        ];
    }
}
```

### Symfony

```php
// In Bundle boot method
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

public function boot()
{
    AdapterRegistry::register(new WeaviateAdapterFactory());
}

// In services.yaml
services:
    weaviate.client.rag:
        class: Weaviate\WeaviateClient
        factory: ['Weaviate\WeaviateClient', 'connectToLocal']
        
    EdgeBinder\EdgeBinder:
        factory: ['EdgeBinder\EdgeBinder', 'fromConfiguration']
        arguments:
            - '%edgebinder.rag%'
            - '@service_container'
```

### Laravel

```php
// In ServiceProvider
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

public function boot()
{
    AdapterRegistry::register(new WeaviateAdapterFactory());
}

public function register()
{
    $this->app->singleton(EdgeBinder::class, function ($app) {
        $config = config('edgebinder.rag');
        return EdgeBinder::fromConfiguration($config, $app);
    });
}
```

## Container Service Requirements

The factory requires a Weaviate client service in your container:

```php
// Example container configuration
$container->set('weaviate.client.rag', function() {
    return WeaviateClient::connectToLocal();
});

// Or for Weaviate Cloud
$container->set('weaviate.client.rag', function() {
    return WeaviateClient::connectToWeaviateCloud(
        'my-cluster.weaviate.network',
        new ApiKey('your-api-key')
    );
});
```

## Error Handling

The factory provides comprehensive error handling:

```php
use EdgeBinder\Exception\AdapterException;

try {
    $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
} catch (AdapterException $e) {
    // Handle configuration or creation errors
    echo "Adapter error: " . $e->getMessage();
}
```

Common error scenarios:
- Missing required configuration keys
- Invalid configuration values
- Container service not found
- Invalid Weaviate client type
- Connection failures

## Multiple Instances

You can create multiple EdgeBinder instances with different configurations:

```php
// RAG instance
$ragConfig = [
    'adapter' => 'weaviate',
    'weaviate_client' => 'weaviate.client.rag',
    'collection_name' => 'RAGBindings',
];

// Social network instance
$socialConfig = [
    'adapter' => 'weaviate',
    'weaviate_client' => 'weaviate.client.social',
    'collection_name' => 'SocialBindings',
];

$ragBinder = EdgeBinder::fromConfiguration($ragConfig, $container);
$socialBinder = EdgeBinder::fromConfiguration($socialConfig, $container);
```

## Testing

For testing, you can clear and mock the registry:

```php
use EdgeBinder\Registry\AdapterRegistry;

// In test setup
AdapterRegistry::clear();
AdapterRegistry::register(new MockWeaviateAdapterFactory());

// In test teardown
AdapterRegistry::clear();
```

## Migration from Direct Instantiation

If you're currently using direct adapter instantiation:

```php
// Old approach
$adapter = new WeaviateAdapter($client, $config);
$binder = new EdgeBinder($adapter);

// New approach
AdapterRegistry::register(new WeaviateAdapterFactory());
$binder = EdgeBinder::fromConfiguration($config, $container);
```

The new approach provides better framework integration and configuration management.
