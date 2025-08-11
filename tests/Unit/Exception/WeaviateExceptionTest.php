<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Exception;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WeaviateException class.
 */
final class WeaviateExceptionTest extends TestCase
{
    public function testExtendsPersistenceException(): void
    {
        $exception = new WeaviateException('test_operation', 'test reason');

        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new WeaviateException('store', 'Failed to store data');

        $this->assertSame('Persistence operation \'store\' failed: Failed to store data', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previousException = new \RuntimeException('Previous error');
        $errorDetails = ['field' => 'value', 'code' => 400];

        $exception = new WeaviateException(
            operation: 'query',
            reason: 'Query failed',
            previous: $previousException,
            retryable: true,
            weaviateErrorCode: 'QUERY_ERROR',
            weaviateErrorDetails: $errorDetails
        );

        $this->assertSame('Persistence operation \'query\' failed: Query failed', $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertSame('QUERY_ERROR', $exception->getWeaviateErrorCode());
        $this->assertSame($errorDetails, $exception->getWeaviateErrorDetails());
    }

    public function testIsRetryableDefaultsFalse(): void
    {
        $exception = new WeaviateException('test', 'test');

        $this->assertFalse($exception->isRetryable());
    }

    public function testIsRetryableCanBeSetTrue(): void
    {
        $exception = new WeaviateException('test', 'test', retryable: true);

        $this->assertTrue($exception->isRetryable());
    }

    public function testGetWeaviateErrorCodeReturnsNull(): void
    {
        $exception = new WeaviateException('test', 'test');

        $this->assertNull($exception->getWeaviateErrorCode());
    }

    public function testGetWeaviateErrorCodeReturnsValue(): void
    {
        $exception = new WeaviateException('test', 'test', weaviateErrorCode: 'ERROR_123');

        $this->assertSame('ERROR_123', $exception->getWeaviateErrorCode());
    }

    public function testGetWeaviateErrorDetailsReturnsNull(): void
    {
        $exception = new WeaviateException('test', 'test');

        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    public function testGetWeaviateErrorDetailsReturnsArray(): void
    {
        $details = ['error' => 'Invalid request', 'status' => 400];
        $exception = new WeaviateException('test', 'test', weaviateErrorDetails: $details);

        $this->assertSame($details, $exception->getWeaviateErrorDetails());
    }

    public function testConnectionErrorFactoryMethod(): void
    {
        $exception = WeaviateException::connectionError('connect', 'Connection timeout');

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertStringContainsString('Weaviate connection error: Connection timeout', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    public function testConnectionErrorWithPreviousException(): void
    {
        $previousException = new \Exception('Network error');
        $exception = WeaviateException::connectionError('connect', 'Failed to connect', $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
    }

    public function testClientErrorFactoryMethod(): void
    {
        $exception = WeaviateException::clientError('validate', 'Invalid data format');

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertStringContainsString('Weaviate client error: Invalid data format', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    public function testClientErrorWithErrorCodeAndDetails(): void
    {
        $details = ['field' => 'name', 'issue' => 'required'];
        $exception = WeaviateException::clientError('validate', 'Validation failed', 'VALIDATION_ERROR', $details);

        $this->assertFalse($exception->isRetryable());
        $this->assertSame('VALIDATION_ERROR', $exception->getWeaviateErrorCode());
        $this->assertSame($details, $exception->getWeaviateErrorDetails());
    }

    public function testServerErrorFactoryMethod(): void
    {
        $exception = WeaviateException::serverError('process', 'Internal server error');

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertStringContainsString('Weaviate server error: Internal server error', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertNull($exception->getWeaviateErrorCode());
        $this->assertNull($exception->getWeaviateErrorDetails());
    }

    public function testServerErrorWithAllParameters(): void
    {
        $previousException = new \RuntimeException('Database connection lost');
        $details = ['server' => 'weaviate-01', 'timestamp' => '2024-01-01T12:00:00Z'];

        $exception = WeaviateException::serverError(
            'backup',
            'Backup failed',
            $previousException,
            'BACKUP_ERROR',
            $details
        );

        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertTrue($exception->isRetryable());
        $this->assertSame('BACKUP_ERROR', $exception->getWeaviateErrorCode());
        $this->assertSame($details, $exception->getWeaviateErrorDetails());
    }

    public function testFactoryMethodsCreateDifferentInstances(): void
    {
        $connection = WeaviateException::connectionError('connect', 'timeout');
        $client = WeaviateException::clientError('validate', 'invalid');
        $server = WeaviateException::serverError('process', 'error');

        $this->assertNotSame($connection, $client);
        $this->assertNotSame($client, $server);
        $this->assertNotSame($connection, $server);
    }

    public function testRetryableConsistency(): void
    {
        // Connection errors should be retryable
        $connection = WeaviateException::connectionError('connect', 'timeout');
        $this->assertTrue($connection->isRetryable());

        // Client errors should not be retryable
        $client = WeaviateException::clientError('validate', 'invalid');
        $this->assertFalse($client->isRetryable());

        // Server errors should be retryable
        $server = WeaviateException::serverError('process', 'error');
        $this->assertTrue($server->isRetryable());
    }

    public function testMessageFormatting(): void
    {
        $connection = WeaviateException::connectionError('connect', 'timeout after 30s');
        $this->assertStringContainsString('Weaviate connection error: timeout after 30s', $connection->getMessage());

        $client = WeaviateException::clientError('validate', 'missing required field');
        $this->assertStringContainsString('Weaviate client error: missing required field', $client->getMessage());

        $server = WeaviateException::serverError('query', 'database unavailable');
        $this->assertStringContainsString('Weaviate server error: database unavailable', $server->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Persistence operation \'test\' failed: Test exception');

        throw new WeaviateException('test', 'Test exception');
    }

    public function testExceptionCanBeCaught(): void
    {
        try {
            throw WeaviateException::connectionError('connect', 'Network timeout');
        } catch (WeaviateException $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertStringContainsString('Network timeout', $e->getMessage());
        }
    }

    public function testExceptionHierarchy(): void
    {
        $exception = new WeaviateException('test', 'test');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(WeaviateException::class, $exception);
    }

    public function testEmptyErrorDetailsArray(): void
    {
        $exception = new WeaviateException('test', 'test', weaviateErrorDetails: []);

        $this->assertSame([], $exception->getWeaviateErrorDetails());
        $this->assertIsArray($exception->getWeaviateErrorDetails());
    }

    public function testEmptyErrorCode(): void
    {
        $exception = new WeaviateException('test', 'test', weaviateErrorCode: '');

        $this->assertSame('', $exception->getWeaviateErrorCode());
    }
}
