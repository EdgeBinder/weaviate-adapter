<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Registry\AdapterFactoryInterface;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

/**
 * Factory for creating Weaviate adapter instances.
 *
 * This factory follows the EdgeBinder v0.6.0 pattern for simple adapter creation.
 */
class WeaviateAdapterFactory implements AdapterFactoryInterface
{
    /**
     * Create Weaviate adapter instance with configuration.
     *
     * @param array<string, mixed> $config Configuration array
     *
     * @return PersistenceAdapterInterface Configured Weaviate adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        $instanceConfig = $config['instance'] ?? [];
        $container = $config['container'] ?? null;

        // Create or get Weaviate client
        $client = $this->createWeaviateClient($instanceConfig, $container);

        // Build adapter configuration
        $adapterConfig = [
            'collection_name' => $instanceConfig['collection_name'] ?? 'EdgeBindings',
            'schema' => $instanceConfig['schema'] ?? ['auto_create' => true],
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
     * Create or get Weaviate client from configuration.
     *
     * @param array<string, mixed>        $config    Instance configuration
     * @param ContainerInterface|null     $container Optional container
     *
     * @return WeaviateClient Weaviate client instance
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    private function createWeaviateClient(array $config, ?ContainerInterface $container): WeaviateClient
    {
        // Try to get client from container first
        if ($container && isset($config['use_container_client']) && $config['use_container_client']) {
            if ($container->has(WeaviateClient::class)) {
                return $container->get(WeaviateClient::class);
            }
        }

        // Create client from configuration
        $host = $config['host'] ?? null;
        $port = $config['port'] ?? null;
        $scheme = $config['scheme'] ?? 'http';

        if (!$host) {
            throw new \InvalidArgumentException('Weaviate host is required');
        }

        if (!$port) {
            throw new \InvalidArgumentException('Weaviate port is required');
        }

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid scheme: ' . $scheme);
        }

        // For now, use the connectToLocal method as it's the most reliable
        // TODO: Support custom host/port/scheme configuration
        return WeaviateClient::connectToLocal();
    }
}
