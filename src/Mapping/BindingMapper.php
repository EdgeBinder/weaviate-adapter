<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Mapping;

use EdgeBinder\Binding;
use EdgeBinder\Contracts\BindingInterface;

/**
 * Maps between EdgeBinder Binding objects and Weaviate data structures.
 *
 * This mapper handles the conversion of EdgeBinder BindingInterface objects
 * to Weaviate-compatible property arrays and vice versa. It delegates
 * metadata handling to the MetadataMapper for proper serialization.
 */
class BindingMapper
{
    private MetadataMapper $metadataMapper;

    public function __construct(MetadataMapper $metadataMapper)
    {
        $this->metadataMapper = $metadataMapper;
    }

    /**
     * Convert a BindingInterface to Weaviate properties array.
     *
     * @param BindingInterface $binding The binding to convert
     * @return array<string, mixed> Weaviate-compatible properties
     */
    public function toWeaviateProperties(BindingInterface $binding): array
    {
        return [
            'bindingId' => $binding->getId(),
            'fromEntityType' => $binding->getFromType(),
            'fromEntityId' => $binding->getFromId(),
            'toEntityType' => $binding->getToType(),
            'toEntityId' => $binding->getToId(),
            'bindingType' => $binding->getType(),
            'metadata' => $this->metadataMapper->serialize($binding->getMetadata()),
            'createdAt' => $binding->getCreatedAt()->format('c'),
            'updatedAt' => $binding->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * Convert a Weaviate object to BindingInterface.
     *
     * @param array<string, mixed> $weaviateObject The Weaviate object data
     * @return BindingInterface The converted binding
     */
    public function fromWeaviateObject(array $weaviateObject): BindingInterface
    {
        // Handle both wrapped (with 'properties') and unwrapped formats
        $props = $weaviateObject['properties'] ?? $weaviateObject;

        // Deserialize metadata
        $metadata = $this->metadataMapper->deserialize($props['metadata'] ?? null);

        return new Binding(
            id: $props['bindingId'],
            fromType: $props['fromEntityType'],
            fromId: $props['fromEntityId'],
            toType: $props['toEntityType'],
            toId: $props['toEntityId'],
            type: $props['bindingType'],
            metadata: $metadata,
            createdAt: new \DateTimeImmutable($props['createdAt']),
            updatedAt: new \DateTimeImmutable($props['updatedAt'])
        );
    }
}
