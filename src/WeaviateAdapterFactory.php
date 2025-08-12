<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterFactoryInterface;
use Weaviate\WeaviateClient;

/**
 * Factory for creating Weaviate adapter instances.
 *
 * This factory follows the EdgeBinder v0.7.0 pattern using AdapterConfiguration.
 */
class WeaviateAdapterFactory implements AdapterFactoryInterface
{
    /**
     * Create Weaviate adapter instance with configuration.
     *
     * @param AdapterConfiguration $config Configuration object
     *
     * @return PersistenceAdapterInterface Configured Weaviate adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
    {
        // Get Weaviate client from container
        $client = $this->getWeaviateClientFromContainer($config);

        // Build adapter configuration using convenience methods
        $adapterConfig = [
            'collection_name' => $config->getInstanceValue('collection_name', 'EdgeBindings'),
            'schema' => $config->getInstanceValue('schema', ['auto_create' => true]),
        ];

        return new WeaviateAdapter($client, $adapterConfig);
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
     * Get Weaviate client from container using configured service name.
     *
     * @param AdapterConfiguration $config Configuration object
     *
     * @return WeaviateClient Weaviate client instance
     *
     * @throws \InvalidArgumentException If client service not available
     */
    private function getWeaviateClientFromContainer(
        AdapterConfiguration $config
    ): WeaviateClient {
        $container = $config->getContainer();

        // Get service name from configuration, fall back to class name
        $serviceName = $config->getInstanceValue('weaviate_client', WeaviateClient::class);

        if (!$container->has($serviceName)) {
            throw new \InvalidArgumentException(
                "WeaviateClient service '{$serviceName}' not found in container. " .
                'Please register a WeaviateClient service in your container.'
            );
        }

        return $container->get($serviceName);
    }
}
