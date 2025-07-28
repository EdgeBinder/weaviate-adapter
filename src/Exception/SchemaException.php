<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Exception;

/**
 * Exception for Weaviate schema-related errors.
 *
 * This exception is thrown when there are issues with:
 * - Collection creation or modification
 * - Property definition conflicts
 * - Vectorizer configuration problems
 * - Schema validation failures
 */
class SchemaException extends WeaviateException
{
    private ?string $collectionName;

    private ?array $schemaDefinition;

    public function __construct(
        string $operation,
        string $reason,
        ?\Throwable $previous = null,
        ?string $collectionName = null,
        ?array $schemaDefinition = null
    ) {
        parent::__construct($operation, $reason, $previous, retryable: false);
        $this->collectionName = $collectionName;
        $this->schemaDefinition = $schemaDefinition;
    }

    /**
     * Get the collection name that caused the schema error.
     */
    public function getCollectionName(): ?string
    {
        return $this->collectionName;
    }

    /**
     * Get the schema definition that caused the error.
     */
    public function getSchemaDefinition(): ?array
    {
        return $this->schemaDefinition;
    }

    /**
     * Create exception for collection creation failure.
     */
    public static function collectionCreationFailed(
        string $collectionName,
        string $reason,
        ?array $schemaDefinition = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            operation: 'schema_creation',
            reason: "Failed to create collection '{$collectionName}': {$reason}",
            previous: $previous,
            collectionName: $collectionName,
            schemaDefinition: $schemaDefinition
        );
    }

    /**
     * Create exception for property definition conflicts.
     */
    public static function propertyConflict(
        string $collectionName,
        string $propertyName,
        string $conflict,
        ?\Throwable $previous = null
    ): self {
        return new self(
            operation: 'schema_property',
            reason: "Property '{$propertyName}' in collection '{$collectionName}' has a conflict: {$conflict}",
            previous: $previous,
            collectionName: $collectionName
        );
    }

    /**
     * Create exception for vectorizer configuration issues.
     */
    public static function vectorizerConfigurationError(
        string $collectionName,
        string $vectorizer,
        string $error,
        ?\Throwable $previous = null
    ): self {
        return new self(
            operation: 'schema_vectorizer',
            reason: "Vectorizer '{$vectorizer}' configuration error for collection '{$collectionName}': {$error}",
            previous: $previous,
            collectionName: $collectionName
        );
    }
}
