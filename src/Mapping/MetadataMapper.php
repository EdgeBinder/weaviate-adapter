<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Mapping;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;

/**
 * Handles metadata serialization and deserialization for Weaviate storage.
 *
 * This mapper converts PHP arrays containing metadata to JSON strings for
 * storage in Weaviate, and converts them back to PHP arrays when retrieving.
 * It handles special data types like DateTime objects and provides proper
 * error handling for serialization failures.
 */
class MetadataMapper
{
    /**
     * Serialize metadata array to JSON string for Weaviate storage.
     *
     * @param array<string, mixed> $metadata The metadata to serialize
     * @return string JSON-encoded metadata
     * @throws WeaviateException If serialization fails
     */
    public function serialize(array $metadata): string
    {
        try {
            // Convert DateTime objects to ISO 8601 strings
            $processedMetadata = $this->processForSerialization($metadata);

            // Use JSON_FORCE_OBJECT to ensure empty arrays become {} instead of []
            $flags = JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION;
            if (empty($processedMetadata)) {
                $flags |= JSON_FORCE_OBJECT;
            }

            return json_encode($processedMetadata, $flags);
        } catch (\JsonException $e) {
            throw new WeaviateException(
                operation: 'serialize_metadata',
                reason: 'Failed to serialize metadata: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Deserialize JSON string to metadata array.
     *
     * @param string|null $json The JSON string to deserialize
     * @return array<string, mixed> Deserialized metadata
     * @throws WeaviateException If deserialization fails
     */
    public function deserialize(?string $json): array
    {
        // Handle null or empty values
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($metadata)) {
                throw new \JsonException('Decoded JSON is not an array');
            }

            // Process the deserialized metadata to restore DateTime objects
            return $this->processForDeserialization($metadata);
        } catch (\JsonException $e) {
            throw new WeaviateException(
                operation: 'deserialize_metadata',
                reason: 'Failed to deserialize metadata: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Process metadata for serialization, converting special types.
     *
     * @param array<string, mixed> $metadata The metadata to process
     * @return array<string, mixed> Processed metadata
     */
    private function processForSerialization(array $metadata): array
    {
        $processed = [];

        foreach ($metadata as $key => $value) {
            $processed[$key] = $this->processValue($value);
        }

        return $processed;
    }

    /**
     * Process a single value for serialization.
     *
     * @param mixed $value The value to process
     * @return mixed Processed value
     */
    private function processValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            // Convert DateTime objects to ISO 8601 strings
            return $value->format('c');
        }

        if (is_array($value)) {
            // Recursively process arrays
            return $this->processForSerialization($value);
        }

        // Return other values as-is (strings, numbers, booleans, null)
        return $value;
    }

    /**
     * Process metadata for deserialization, converting special types back.
     *
     * @param array<string, mixed> $metadata The metadata to process
     * @return array<string, mixed> Processed metadata
     */
    private function processForDeserialization(array $metadata): array
    {
        $processed = [];

        foreach ($metadata as $key => $value) {
            $processed[$key] = $this->processValueForDeserialization($value);
        }

        return $processed;
    }

    /**
     * Process a single value for deserialization.
     *
     * @param mixed $value The value to process
     * @return mixed Processed value
     */
    private function processValueForDeserialization(mixed $value): mixed
    {
        if (is_string($value) && $this->isIso8601DateTime($value)) {
            // Convert ISO 8601 strings back to DateTimeImmutable objects
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                // If parsing fails, return the original string
                return $value;
            }
        }

        if (is_array($value)) {
            // Recursively process arrays
            return $this->processForDeserialization($value);
        }

        // Return other values as-is (numbers, booleans, null)
        return $value;
    }

    /**
     * Check if a string looks like an ISO 8601 datetime.
     *
     * @param string $value The string to check
     * @return bool True if it looks like an ISO 8601 datetime
     */
    private function isIso8601DateTime(string $value): bool
    {
        // Check for ISO 8601 format: YYYY-MM-DDTHH:MM:SS+TZ or similar
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/', $value);
    }
}
