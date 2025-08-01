<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Registry\AdapterFactoryInterface;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

/**
 * Factory for creating Weaviate adapter instances.
 *
 * This factory implements the EdgeBinder extensible adapter system,
 * allowing Weaviate adapters to be created consistently across all PHP frameworks.
 *
 * Example usage:
 * ```php
 * // Register the factory
 * AdapterRegistry::register(new WeaviateAdapterFactory());
 *
 * // Create adapter through EdgeBinder
 * $config = [
 *     'adapter' => 'weaviate',
 *     'weaviate_client' => 'weaviate.client.rag',
 *     'collection_name' => 'RAGBindings',
 *     'schema' => ['auto_create' => true],
 * ];
 *
 * $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
 * ```
 */
class WeaviateAdapterFactory implements AdapterFactoryInterface
{
    /**
     * Create Weaviate adapter instance with configuration.
     *
     * The configuration array contains:
     * - 'instance': instance-specific configuration
     * - 'global': global EdgeBinder configuration
     * - 'container': PSR-11 container for dependency injection
     *
     * Instance configuration supports:
     * - 'weaviate_client': Container service name for Weaviate client (required)
     * - 'collection_name': Weaviate collection name (default: 'EdgeBindings')
     * - 'schema': Schema configuration array (default: auto_create enabled)
     * - 'vectorizer': Vectorizer configuration array
     * - 'performance': Performance tuning configuration array
     *
     * @param array<string, mixed> $config Configuration array
     *
     * @return PersistenceAdapterInterface Configured Weaviate adapter instance
     *
     * @throws AdapterException If configuration is invalid or adapter creation fails
     */
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        try {
            $this->validateConfiguration($config);

            $container = $config['container'];
            $instanceConfig = $config['instance'];
            $globalConfig = $config['global'];

            // Get Weaviate client from container
            $weaviateClientService = $instanceConfig['weaviate_client'] ?? 'weaviate.client.default';
            $weaviateClient = $this->getWeaviateClient($container, $weaviateClientService);

            // Build adapter configuration from instance config
            $adapterConfig = $this->buildAdapterConfig($instanceConfig, $globalConfig);

            return new WeaviateAdapter($weaviateClient, $adapterConfig);
        } catch (\Throwable $e) {
            if ($e instanceof AdapterException) {
                throw $e;
            }

            throw AdapterException::creationFailed(
                $this->getAdapterType(),
                $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Get the adapter type identifier.
     *
     * @return string The adapter type 'weaviate'
     */
    public function getAdapterType(): string
    {
        return 'weaviate';
    }

    /**
     * Validate the configuration structure.
     *
     * @param array<string, mixed> $config Configuration to validate
     *
     * @throws AdapterException If configuration is invalid
     */
    private function validateConfiguration(array $config): void
    {
        $required = ['container', 'instance', 'global'];
        $missing = [];

        foreach ($required as $key) {
            if (!isset($config[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw AdapterException::missingConfiguration($this->getAdapterType(), $missing);
        }

        if (!$config['container'] instanceof ContainerInterface) {
            throw AdapterException::invalidConfiguration(
                $this->getAdapterType(),
                'Container must implement Psr\Container\ContainerInterface'
            );
        }

        if (!is_array($config['instance'])) {
            throw AdapterException::invalidConfiguration(
                $this->getAdapterType(),
                'Instance configuration must be an array'
            );
        }

        if (!is_array($config['global'])) {
            throw AdapterException::invalidConfiguration(
                $this->getAdapterType(),
                'Global configuration must be an array'
            );
        }
    }

    /**
     * Get Weaviate client from container.
     *
     * @param ContainerInterface $container PSR-11 container
     * @param string $serviceName Service name for Weaviate client
     *
     * @return WeaviateClient Weaviate client instance
     *
     * @throws AdapterException If Weaviate client cannot be retrieved or is invalid
     */
    private function getWeaviateClient(ContainerInterface $container, string $serviceName): WeaviateClient
    {
        try {
            if (!$container->has($serviceName)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    "Weaviate client service '{$serviceName}' not found in container"
                );
            }

            $client = $container->get($serviceName);

            if (!$client instanceof WeaviateClient) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    "Service '{$serviceName}' must return a WeaviateClient instance, got " . get_class($client)
                );
            }

            // Test Weaviate connection by trying to access collections
            try {
                $client->collections();
            } catch (\Throwable $e) {
                throw AdapterException::creationFailed(
                    $this->getAdapterType(),
                    'Weaviate client connection test failed: ' . $e->getMessage(),
                    $e
                );
            }

            return $client;
        } catch (\Throwable $e) {
            if ($e instanceof AdapterException) {
                throw $e;
            }

            throw AdapterException::creationFailed(
                $this->getAdapterType(),
                "Failed to get Weaviate client '{$serviceName}': " . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Build adapter configuration from instance and global config.
     *
     * @param array<string, mixed> $instanceConfig Instance-specific configuration
     * @param array<string, mixed> $globalConfig Global EdgeBinder configuration
     *
     * @return array<string, mixed> Adapter configuration
     */
    private function buildAdapterConfig(array $instanceConfig, array $globalConfig): array
    {
        $config = [];

        // Extract collection name
        if (isset($instanceConfig['collection_name'])) {
            $collectionName = $instanceConfig['collection_name'];
            if (!is_string($collectionName) || empty($collectionName)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Collection name must be a non-empty string'
                );
            }
            $config['collection_name'] = $collectionName;
        }

        // Extract schema configuration
        if (isset($instanceConfig['schema'])) {
            $schema = $instanceConfig['schema'];
            if (!is_array($schema)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Schema configuration must be an array'
                );
            }
            $config['schema'] = $schema;
        }

        // Extract vectorizer configuration
        if (isset($instanceConfig['vectorizer'])) {
            $vectorizer = $instanceConfig['vectorizer'];
            if (!is_array($vectorizer)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Vectorizer configuration must be an array'
                );
            }
            $config['vectorizer'] = $vectorizer;
        }

        // Extract performance configuration
        if (isset($instanceConfig['performance'])) {
            $performance = $instanceConfig['performance'];
            if (!is_array($performance)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Performance configuration must be an array'
                );
            }
            $config['performance'] = $performance;
        }

        // Use global configuration for debug settings if available
        if (isset($globalConfig['debug']) && $globalConfig['debug']) {
            $config['debug'] = true;
        }

        return $config;
    }
}
