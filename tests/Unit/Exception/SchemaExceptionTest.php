<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Exception;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SchemaException class.
 */
final class SchemaExceptionTest extends TestCase
{
    public function testExtendsWeaviateException(): void
    {
        $exception = new SchemaException('test_operation', 'test reason');
        
        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new SchemaException('schema_create', 'Schema creation failed');
        
        $this->assertStringContainsString('Schema creation failed', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable()); // Schema exceptions are not retryable
        $this->assertNull($exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previousException = new \RuntimeException('Database error');
        $schemaDefinition = [
            'class' => 'TestCollection',
            'properties' => [
                ['name' => 'content', 'dataType' => ['text']],
            ],
        ];
        
        $exception = new SchemaException(
            operation: 'schema_update',
            reason: 'Schema update failed',
            previous: $previousException,
            collectionName: 'TestCollection',
            schemaDefinition: $schemaDefinition
        );
        
        $this->assertStringContainsString('Schema update failed', $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertSame('TestCollection', $exception->getCollectionName());
        $this->assertSame($schemaDefinition, $exception->getSchemaDefinition());
    }

    public function testGetCollectionNameReturnsNull(): void
    {
        $exception = new SchemaException('test', 'test');
        
        $this->assertNull($exception->getCollectionName());
    }

    public function testGetCollectionNameReturnsValue(): void
    {
        $exception = new SchemaException('test', 'test', collectionName: 'MyCollection');
        
        $this->assertSame('MyCollection', $exception->getCollectionName());
    }

    public function testGetSchemaDefinitionReturnsNull(): void
    {
        $exception = new SchemaException('test', 'test');
        
        $this->assertNull($exception->getSchemaDefinition());
    }

    public function testGetSchemaDefinitionReturnsArray(): void
    {
        $schema = ['class' => 'Test', 'properties' => []];
        $exception = new SchemaException('test', 'test', schemaDefinition: $schema);
        
        $this->assertSame($schema, $exception->getSchemaDefinition());
    }

    public function testCollectionCreationFailedFactoryMethod(): void
    {
        $exception = SchemaException::collectionCreationFailed(
            'EdgeBindings',
            'Collection already exists'
        );
        
        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertStringContainsString("Failed to create collection 'EdgeBindings'", $exception->getMessage());
        $this->assertStringContainsString('Collection already exists', $exception->getMessage());
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
    }

    public function testCollectionCreationFailedWithSchemaDefinition(): void
    {
        $schemaDefinition = [
            'class' => 'EdgeBindings',
            'vectorizer' => 'none',
            'properties' => [
                ['name' => 'content', 'dataType' => ['text']],
            ],
        ];
        
        $exception = SchemaException::collectionCreationFailed(
            'EdgeBindings',
            'Invalid vectorizer configuration',
            $schemaDefinition
        );
        
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
        $this->assertSame($schemaDefinition, $exception->getSchemaDefinition());
    }

    public function testCollectionCreationFailedWithPreviousException(): void
    {
        $previousException = new \Exception('Network timeout');
        
        $exception = SchemaException::collectionCreationFailed(
            'EdgeBindings',
            'Connection failed',
            null,
            $previousException
        );
        
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testPropertyConflictFactoryMethod(): void
    {
        $exception = SchemaException::propertyConflict(
            'EdgeBindings',
            'content',
            'Type mismatch: expected text, got number'
        );
        
        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertStringContainsString("Property 'content' in collection 'EdgeBindings'", $exception->getMessage());
        $this->assertStringContainsString('Type mismatch: expected text, got number', $exception->getMessage());
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
    }

    public function testPropertyConflictWithPreviousException(): void
    {
        $previousException = new \RuntimeException('Schema validation failed');
        
        $exception = SchemaException::propertyConflict(
            'EdgeBindings',
            'metadata',
            'Property already exists with different type',
            $previousException
        );
        
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
    }

    public function testVectorizerConfigurationErrorFactoryMethod(): void
    {
        $exception = SchemaException::vectorizerConfigurationError(
            'EdgeBindings',
            'text2vec-openai',
            'Invalid API key provided'
        );
        
        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertStringContainsString("Vectorizer 'text2vec-openai' configuration error", $exception->getMessage());
        $this->assertStringContainsString("for collection 'EdgeBindings'", $exception->getMessage());
        $this->assertStringContainsString('Invalid API key provided', $exception->getMessage());
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
    }

    public function testVectorizerConfigurationErrorWithPreviousException(): void
    {
        $previousException = new \Exception('HTTP 401 Unauthorized');
        
        $exception = SchemaException::vectorizerConfigurationError(
            'EdgeBindings',
            'text2vec-huggingface',
            'Authentication failed',
            $previousException
        );
        
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertSame('EdgeBindings', $exception->getCollectionName());
    }

    public function testFactoryMethodsCreateDifferentInstances(): void
    {
        $creation = SchemaException::collectionCreationFailed('Test', 'reason');
        $property = SchemaException::propertyConflict('Test', 'prop', 'conflict');
        $vectorizer = SchemaException::vectorizerConfigurationError('Test', 'vec', 'error');
        
        $this->assertNotSame($creation, $property);
        $this->assertNotSame($property, $vectorizer);
        $this->assertNotSame($creation, $vectorizer);
    }

    public function testAllFactoryMethodsAreNotRetryable(): void
    {
        $creation = SchemaException::collectionCreationFailed('Test', 'reason');
        $property = SchemaException::propertyConflict('Test', 'prop', 'conflict');
        $vectorizer = SchemaException::vectorizerConfigurationError('Test', 'vec', 'error');
        
        $this->assertFalse($creation->isRetryable());
        $this->assertFalse($property->isRetryable());
        $this->assertFalse($vectorizer->isRetryable());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage("Failed to create collection 'Test'");
        
        throw SchemaException::collectionCreationFailed('Test', 'Schema validation failed');
    }

    public function testExceptionCanBeCaught(): void
    {
        try {
            throw SchemaException::propertyConflict('EdgeBindings', 'content', 'Type conflict');
        } catch (SchemaException $e) {
            $this->assertSame('EdgeBindings', $e->getCollectionName());
            $this->assertFalse($e->isRetryable());
            $this->assertStringContainsString('Type conflict', $e->getMessage());
        }
    }

    public function testExceptionHierarchy(): void
    {
        $exception = new SchemaException('test', 'test');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertInstanceOf(SchemaException::class, $exception);
    }

    public function testEmptySchemaDefinitionArray(): void
    {
        $exception = new SchemaException('test', 'test', schemaDefinition: []);
        
        $this->assertSame([], $exception->getSchemaDefinition());
        $this->assertIsArray($exception->getSchemaDefinition());
    }

    public function testEmptyCollectionName(): void
    {
        $exception = new SchemaException('test', 'test', collectionName: '');
        
        $this->assertSame('', $exception->getCollectionName());
    }

    public function testComplexSchemaDefinition(): void
    {
        $complexSchema = [
            'class' => 'ComplexCollection',
            'vectorizer' => 'text2vec-openai',
            'moduleConfig' => [
                'text2vec-openai' => [
                    'model' => 'ada',
                    'modelVersion' => '002',
                ],
            ],
            'properties' => [
                [
                    'name' => 'content',
                    'dataType' => ['text'],
                    'moduleConfig' => [
                        'text2vec-openai' => [
                            'skip' => false,
                            'vectorizePropertyName' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'metadata',
                    'dataType' => ['object'],
                ],
            ],
        ];
        
        $exception = new SchemaException(
            'schema_validation',
            'Complex schema validation failed',
            schemaDefinition: $complexSchema
        );
        
        $this->assertSame($complexSchema, $exception->getSchemaDefinition());
        $this->assertIsArray($exception->getSchemaDefinition());
        $this->assertArrayHasKey('class', $exception->getSchemaDefinition());
        $this->assertArrayHasKey('properties', $exception->getSchemaDefinition());
    }
}
