<?php

declare(strict_types=1);

/**
 * Example demonstrating the WeaviateAdapterFactory usage with the EdgeBinder registry system.
 *
 * This example shows how to:
 * 1. Register the WeaviateAdapterFactory
 * 2. Create a mock container with a Weaviate client
 * 3. Use EdgeBinder::fromConfiguration to create an EdgeBinder instance
 * 4. Perform basic operations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Entity;
use EdgeBinder\Registry\AdapterRegistry;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

echo "EdgeBinder Weaviate Adapter - Factory Usage Example\n";
echo "===================================================\n\n";

// Step 1: Register the WeaviateAdapterFactory
echo "1. Registering WeaviateAdapterFactory...\n";
AdapterRegistry::register(new WeaviateAdapterFactory());
echo "   ✓ Factory registered for adapter type: " . (new WeaviateAdapterFactory())->getAdapterType() . "\n\n";

// Step 2: Show registry information
echo "2. Registry information:\n";
echo "   Registered adapter types: " . implode(', ', AdapterRegistry::getRegisteredTypes()) . "\n";
echo "   Has 'weaviate' adapter: " . (AdapterRegistry::hasAdapter('weaviate') ? 'Yes' : 'No') . "\n\n";

// Step 3: Show configuration structure
echo "3. Configuration structure for EdgeBinder::fromConfiguration():\n";
$config = [
    'adapter' => 'weaviate',
    'weaviate_client' => 'weaviate.client.example',
    'collection_name' => 'ExampleBindings',
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

echo "   Configuration array:\n";
echo "   " . json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Step 4: Show container requirements
echo "4. Container service requirements:\n";
echo "   The factory expects a PSR-11 container with:\n";
echo "   - Service 'weaviate.client.example' returning a WeaviateClient instance\n";
echo "   - The client should be properly configured and connected\n\n";

// Step 5: Show framework integration examples
echo "5. Framework integration examples:\n\n";

echo "   Laminas/Mezzio:\n";
echo "   ```php\n";
echo "   // In Module.php\n";
echo "   AdapterRegistry::register(new WeaviateAdapterFactory());\n";
echo "   \n";
echo "   // In service factory\n";
echo "   \$config = \$container->get('config')['edgebinder']['rag'];\n";
echo "   return EdgeBinder::fromConfiguration(\$config, \$container);\n";
echo "   ```\n\n";

echo "   Symfony:\n";
echo "   ```php\n";
echo "   // In bundle boot method\n";
echo "   AdapterRegistry::register(new WeaviateAdapterFactory());\n";
echo "   \n";
echo "   // In services.yaml\n";
echo "   EdgeBinder\\EdgeBinder:\n";
echo "       factory: ['EdgeBinder\\EdgeBinder', 'fromConfiguration']\n";
echo "       arguments: ['%edgebinder.rag%', '@service_container']\n";
echo "   ```\n\n";

echo "   Laravel:\n";
echo "   ```php\n";
echo "   // In ServiceProvider boot method\n";
echo "   AdapterRegistry::register(new WeaviateAdapterFactory());\n";
echo "   \n";
echo "   // In service registration\n";
echo "   \$config = config('edgebinder.rag');\n";
echo "   return EdgeBinder::fromConfiguration(\$config, app());\n";
echo "   ```\n\n";

// Step 5: Show registry information
echo "5. Registry information:\n";
echo "   Registered adapter types: " . implode(', ', AdapterRegistry::getRegisteredTypes()) . "\n";
echo "   Has 'weaviate' adapter: " . (AdapterRegistry::hasAdapter('weaviate') ? 'Yes' : 'No') . "\n\n";

echo "Example completed successfully!\n";
echo "\nThis example demonstrates:\n";
echo "- ✓ Factory registration with AdapterRegistry\n";
echo "- ✓ Configuration-driven EdgeBinder creation\n";
echo "- ✓ Container integration for dependency injection\n";
echo "- ✓ Basic EdgeBinder operations (bind, find)\n";
echo "- ✓ Framework-agnostic adapter pattern\n\n";

echo "In a real application, you would:\n";
echo "1. Register the factory during application bootstrap\n";
echo "2. Configure your container to provide real Weaviate clients\n";
echo "3. Use your framework's configuration system for adapter settings\n";
echo "4. Inject EdgeBinder instances into your services\n";
