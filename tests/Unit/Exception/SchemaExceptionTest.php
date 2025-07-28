<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Exception;

use EdgeBinder\Adapter\Weaviate\Exception\SchemaException;
use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SchemaException.
 */
class SchemaExceptionTest extends TestCase
{
    /**
     * Test basic exception construction.
     */
    public function testBasicConstruction(): void
    {
        $operation = 'schema_creation';
        $reason = 'Collection already exists';
        $previous = new \Exception('Duplicate collection');
        $collectionName = 'TestBindings';
        $schemaDefinition = [
            'properties' => [
                ['name' => 'bindingId', 'dataType' => ['text']],
            ],
        ];

        $exception = new SchemaException(
            $operation,
            $reason,
            $previous,
            $collectionName,
            $schemaDefinition
        );

        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertEquals("Persistence operation 'schema_creation' failed: Collection already exists", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertFalse($exception->isRetryable()); // Schema exceptions are not retryable
        $this->assertEquals($collectionName, $exception->getCollectionName());
        $this->assertEquals($schemaDefinition, $exception->getSchemaDefinition());
    }

    /**
     * Test construction with minimal parameters.
     */
    public function testMinimalConstruction(): void
    {
        $exception = new SchemaException('schema_update', 'Property conflict');

        $this->assertEquals("Persistence operation 'schema_update' failed: Property conflict", $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertNull($exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
    }

    /**
     * Test collectionCreationFailed factory method.
     */
    public function testCollectionCreationFailed(): void
    {
        $collectionName = 'EdgeBindings';
        $reason = 'Invalid property definition';
        $schemaDefinition = [
            'properties' => [
                ['name' => 'invalidProperty', 'dataType' => ['unknown']],
            ],
            'vectorizer' => 'text2vec-openai',
        ];
        $previous = new \Exception('Schema validation failed');

        $exception = SchemaException::collectionCreationFailed(
            $collectionName,
            $reason,
            $schemaDefinition,
            $previous
        );

        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertEquals("Persistence operation 'schema_creation' failed: Failed to create collection 'EdgeBindings': Invalid property definition", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertEquals($collectionName, $exception->getCollectionName());
        $this->assertEquals($schemaDefinition, $exception->getSchemaDefinition());
    }

    /**
     * Test collectionCreationFailed with minimal parameters.
     */
    public function testCollectionCreationFailedMinimal(): void
    {
        $collectionName = 'TestCollection';
        $reason = 'Connection timeout';

        $exception = SchemaException::collectionCreationFailed($collectionName, $reason);

        $this->assertEquals("Persistence operation 'schema_creation' failed: Failed to create collection 'TestCollection': Connection timeout", $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals($collectionName, $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
    }

    /**
     * Test propertyConflict factory method.
     */
    public function testPropertyConflict(): void
    {
        $collectionName = 'Bindings';
        $propertyName = 'metadata';
        $conflict = 'Type mismatch: expected object, got text';
        $previous = new \Exception('Property validation error');

        $exception = SchemaException::propertyConflict(
            $collectionName,
            $propertyName,
            $conflict,
            $previous
        );

        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertEquals("Persistence operation 'schema_property' failed: Property 'metadata' in collection 'Bindings' has a conflict: Type mismatch: expected object, got text", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertEquals($collectionName, $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
    }

    /**
     * Test propertyConflict with minimal parameters.
     */
    public function testPropertyConflictMinimal(): void
    {
        $collectionName = 'TestCollection';
        $propertyName = 'testProperty';
        $conflict = 'Duplicate property name';

        $exception = SchemaException::propertyConflict($collectionName, $propertyName, $conflict);

        $this->assertEquals("Persistence operation 'schema_property' failed: Property 'testProperty' in collection 'TestCollection' has a conflict: Duplicate property name", $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals($collectionName, $exception->getCollectionName());
    }

    /**
     * Test vectorizerConfigurationError factory method.
     */
    public function testVectorizerConfigurationError(): void
    {
        $collectionName = 'Documents';
        $vectorizer = 'text2vec-openai';
        $error = 'Invalid API key configuration';
        $previous = new \Exception('Authentication failed');

        $exception = SchemaException::vectorizerConfigurationError(
            $collectionName,
            $vectorizer,
            $error,
            $previous
        );

        $this->assertInstanceOf(SchemaException::class, $exception);
        $this->assertEquals("Persistence operation 'schema_vectorizer' failed: Vectorizer 'text2vec-openai' configuration error for collection 'Documents': Invalid API key configuration", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertFalse($exception->isRetryable());
        $this->assertEquals($collectionName, $exception->getCollectionName());
        $this->assertNull($exception->getSchemaDefinition());
    }

    /**
     * Test vectorizerConfigurationError with minimal parameters.
     */
    public function testVectorizerConfigurationErrorMinimal(): void
    {
        $collectionName = 'TestCollection';
        $vectorizer = 'text2vec-contextionary';
        $error = 'Vectorizer not available';

        $exception = SchemaException::vectorizerConfigurationError($collectionName, $vectorizer, $error);

        $this->assertEquals("Persistence operation 'schema_vectorizer' failed: Vectorizer 'text2vec-contextionary' configuration error for collection 'TestCollection': Vectorizer not available", $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals($collectionName, $exception->getCollectionName());
    }

    /**
     * Test exception inheritance chain.
     */
    public function testInheritanceChain(): void
    {
        $exception = new SchemaException('test', 'test reason');

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(PersistenceException::class, $exception);
        $this->assertInstanceOf(WeaviateException::class, $exception);
        $this->assertInstanceOf(SchemaException::class, $exception);
    }

    /**
     * Test schema definition preservation.
     */
    public function testSchemaDefinitionPreservation(): void
    {
        $complexSchema = [
            'class' => 'ComplexBindings',
            'properties' => [
                [
                    'name' => 'bindingId',
                    'dataType' => ['text'],
                    'indexFilterable' => true,
                    'indexSearchable' => false,
                ],
                [
                    'name' => 'metadata',
                    'dataType' => ['object'],
                    'nestedProperties' => [
                        ['name' => 'tags', 'dataType' => ['text[]']],
                        ['name' => 'score', 'dataType' => ['number']],
                    ],
                ],
            ],
            'vectorizer' => 'text2vec-transformers',
            'vectorIndexConfig' => [
                'distance' => 'cosine',
                'ef' => 64,
                'efConstruction' => 128,
            ],
        ];

        $exception = SchemaException::collectionCreationFailed(
            'ComplexBindings',
            'Complex schema validation failed',
            $complexSchema
        );

        $retrievedSchema = $exception->getSchemaDefinition();
        $this->assertEquals($complexSchema, $retrievedSchema);
        $this->assertNotNull($retrievedSchema);
        $this->assertIsArray($retrievedSchema['properties']);
        $this->assertIsArray($retrievedSchema['vectorIndexConfig']);
        $this->assertEquals('text2vec-transformers', $retrievedSchema['vectorizer']);
    }

    /**
     * Test that schema exceptions are always non-retryable.
     */
    public function testSchemaExceptionsAreNonRetryable(): void
    {
        $creationException = SchemaException::collectionCreationFailed('Test', 'reason');
        $propertyException = SchemaException::propertyConflict('Test', 'prop', 'conflict');
        $vectorizerException = SchemaException::vectorizerConfigurationError('Test', 'vec', 'error');

        $this->assertFalse($creationException->isRetryable());
        $this->assertFalse($propertyException->isRetryable());
        $this->assertFalse($vectorizerException->isRetryable());
    }
}
