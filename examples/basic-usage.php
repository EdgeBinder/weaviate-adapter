<?php

declare(strict_types=1);

/**
 * Basic usage example for EdgeBinder Weaviate Adapter
 *
 * This example demonstrates how the adapter will be used once implemented.
 * Currently, this is a placeholder showing the intended API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "EdgeBinder Weaviate Adapter - Basic Usage Example\n";
echo "=================================================\n\n";

// Example of what the adapter usage will look like:
echo "This is how the adapter will be used once implemented:\n\n";

echo <<<'PHP'
use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Entity;

// Create the adapter
$adapter = new WeaviateAdapter($weaviateClient, [
    'collection_name' => 'MyAppBindings',
    'schema' => [
        'auto_create' => true,
        'vectorizer' => 'text2vec-openai'
    ]
]);

// Create EdgeBinder instance
$binder = new EdgeBinder($adapter);

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
        'confidence_score' => 0.95,
        'semantic_similarity' => 0.87,
        'tags' => ['production', 'critical']
    ]
);

echo "Created binding: " . $binding->getId() . "\n";

// Find the binding
$foundBinding = $binder->find($binding->getId());
if ($foundBinding) {
    echo "Found binding with metadata: " . json_encode($foundBinding->getMetadata()) . "\n";
}

// Query bindings
$bindings = $binder->query()
    ->from($workspace)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();

echo "Found " . count($bindings) . " write access bindings\n";

// Phase 2: Vector similarity queries (future)
$similarBindings = $adapter->findSimilarBindings(
    $referenceBinding,
    threshold: 0.8,
    limit: 10
);

$conceptualBindings = $adapter->findBySemanticConcepts(
    concepts: ['access control', 'permissions', 'security'],
    certainty: 0.7
);
PHP;

echo "\n\nThis example will work once the WeaviateAdapter class is implemented.\n";
echo "For now, you can run integration tests to verify Weaviate connectivity:\n";
echo "composer test-integration\n";
