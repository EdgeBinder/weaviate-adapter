<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Exception;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WeaviateException.
 */
class WeaviateExceptionTest extends TestCase
{
    /**
     * Test basic exception construction.
     */
    public function testBasicConstruction(): void
    {
        $operation = 'store';
        $reason = 'Connection timeout';
        $previous = new \Exception('Network error');
        $retryable = true;
        $errorCode = 'TIMEOUT';
        $errorDetails = ['timeout' => 30, 'host' => 'localhost'];

        $exception = new WeaviateException(
            $operation,
            $reason,
            $previous,
            $retryable,
            $errorCode,
            $errorDetails
        );

        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertEquals("Persistence operation 'store' failed: Connection timeout", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertEquals($errorCode, $exception->getWeaviateErrorCode());
        $this->assertEquals($errorDetails, $exception->getWeaviateErrorDetails());
    }

    /**
     * Test non-retryable exception.
     */
    public function testNonRetryableException(): void
    {
        $exception = new WeaviateException('delete', 'Invalid ID format', retryable: false);

        $this->assertFalse($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    /**
     * Test connectionError factory method.
     */
    public function testConnectionError(): void
    {
        $operation = 'find';
        $message = 'Unable to connect to Weaviate server';
        $previous = new \Exception('Connection refused');

        $exception = WeaviateException::connectionError($operation, $message, $previous);

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertEquals("Persistence operation 'find' failed: Weaviate connection error: Unable to connect to Weaviate server", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    /**
     * Test clientError factory method.
     */
    public function testClientError(): void
    {
        $operation = 'store';
        $message = 'Invalid binding data';
        $errorCode = 'VALIDATION_ERROR';
        $details = ['field' => 'bindingType', 'issue' => 'required'];

        $exception = WeaviateException::clientError($operation, $message, $errorCode, $details);

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertEquals("Persistence operation 'store' failed: Weaviate client error: Invalid binding data", $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertEquals($errorCode, $exception->getWeaviateErrorCode());
        $this->assertEquals($details, $exception->getWeaviateErrorDetails());
    }

    /**
     * Test serverError factory method.
     */
    public function testServerError(): void
    {
        $operation = 'update';
        $message = 'Internal server error';
        $previous = new \Exception('Database connection lost');
        $errorCode = 'INTERNAL_ERROR';
        $details = ['status' => 500, 'timestamp' => '2024-01-01T00:00:00Z'];

        $exception = WeaviateException::serverError($operation, $message, $previous, $errorCode, $details);

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertEquals("Persistence operation 'update' failed: Weaviate server error: Internal server error", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertEquals($errorCode, $exception->getWeaviateErrorCode());
        $this->assertEquals($details, $exception->getWeaviateErrorDetails());
    }

    /**
     * Test factory methods with minimal parameters.
     */
    public function testFactoryMethodsMinimalParameters(): void
    {
        // Connection error with minimal params
        $connectionException = WeaviateException::connectionError('test', 'connection failed');
        $this->assertTrue($connectionException->isRetryable());
        $this->assertNull($connectionException->getPrevious());

        // Client error with minimal params
        $clientException = WeaviateException::clientError('test', 'client error');
        $this->assertFalse($clientException->isRetryable());
        $this->assertNull($clientException->getWeaviateErrorCode());
        $this->assertNull($clientException->getWeaviateErrorDetails());

        // Server error with minimal params
        $serverException = WeaviateException::serverError('test', 'server error');
        $this->assertTrue($serverException->isRetryable());
        $this->assertNull($serverException->getPrevious());
        $this->assertNull($serverException->getWeaviateErrorCode());
        $this->assertNull($serverException->getWeaviateErrorDetails());
    }

    /**
     * Test exception inheritance chain.
     */
    public function testInheritanceChain(): void
    {
        $exception = new WeaviateException('test', 'test reason');

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(WeaviateException::class, $exception);
    }

    /**
     * Test error details serialization.
     */
    public function testErrorDetailsSerialization(): void
    {
        $complexDetails = [
            'nested' => ['array' => ['value1', 'value2']],
            'object' => (object) ['property' => 'value'],
            'number' => 42,
            'boolean' => true,
            'null' => null,
        ];

        $exception = WeaviateException::clientError('test', 'test', 'CODE', $complexDetails);

        $retrievedDetails = $exception->getWeaviateErrorDetails();
        $this->assertEquals($complexDetails, $retrievedDetails);
        $this->assertNotNull($retrievedDetails);
        $this->assertIsArray($retrievedDetails['nested']);
        $this->assertIsObject($retrievedDetails['object']);
        $this->assertIsInt($retrievedDetails['number']);
        $this->assertIsBool($retrievedDetails['boolean']);
        $this->assertNull($retrievedDetails['null']);
    }
}
