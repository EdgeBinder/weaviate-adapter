<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Exception;

use EdgeBinder\Exception\PersistenceException;

/**
 * Base exception for Weaviate-specific errors.
 *
 * This exception provides additional context for Weaviate-related failures
 * and includes retry logic capabilities for transient errors.
 */
class WeaviateException extends PersistenceException
{
    public function __construct(
        string $operation,
        string $reason,
        ?\Throwable $previous = null,
        private bool $retryable = false,
        private ?string $weaviateErrorCode = null,
        private ?array $weaviateErrorDetails = null,
    ) {
        parent::__construct($operation, $reason, $previous);
    }

    /**
     * Check if this error is retryable.
     *
     * Retryable errors are typically transient issues like:
     * - Network timeouts
     * - Temporary service unavailability
     * - Rate limiting
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Get the Weaviate-specific error code if available.
     */
    public function getWeaviateErrorCode(): ?string
    {
        return $this->weaviateErrorCode;
    }

    /**
     * Get additional error details from Weaviate if available.
     */
    public function getWeaviateErrorDetails(): ?array
    {
        return $this->weaviateErrorDetails;
    }

    /**
     * Create a retryable exception for connection issues.
     */
    public static function connectionError(string $operation, string $message, ?\Throwable $previous = null): self
    {
        return new self(
            operation: $operation,
            reason: "Weaviate connection error: {$message}",
            previous: $previous,
            retryable: true
        );
    }

    /**
     * Create a non-retryable exception for client errors.
     */
    public static function clientError(string $operation, string $message, ?string $errorCode = null, ?array $details = null): self
    {
        return new self(
            operation: $operation,
            reason: "Weaviate client error: {$message}",
            retryable: false,
            weaviateErrorCode: $errorCode,
            weaviateErrorDetails: $details
        );
    }

    /**
     * Create a retryable exception for server errors.
     */
    public static function serverError(string $operation, string $message, ?\Throwable $previous = null, ?string $errorCode = null, ?array $details = null): self
    {
        return new self(
            operation: $operation,
            reason: "Weaviate server error: {$message}",
            previous: $previous,
            retryable: true,
            weaviateErrorCode: $errorCode,
            weaviateErrorDetails: $details
        );
    }
}
