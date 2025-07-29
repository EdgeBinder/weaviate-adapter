<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Mapping;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MetadataMapper.
 */
class MetadataMapperTest extends TestCase
{
    private MetadataMapper $metadataMapper;

    protected function setUp(): void
    {
        $this->metadataMapper = new MetadataMapper();
    }

    /**
     * Test serializing simple metadata array.
     */
    public function testSerializeSimpleMetadata(): void
    {
        $metadata = [
            'access_level' => 'write',
            'granted_by' => 'admin',
            'confidence_score' => 0.95,
        ];

        $result = $this->metadataMapper->serialize($metadata);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertEquals($metadata, $decoded);
    }

    /**
     * Test serializing empty metadata array.
     */
    public function testSerializeEmptyMetadata(): void
    {
        $result = $this->metadataMapper->serialize([]);

        $this->assertEquals('{}', $result);
    }

    /**
     * Test serializing metadata with DateTime objects.
     */
    public function testSerializeMetadataWithDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2024-01-15T10:30:00Z');
        $metadata = [
            'granted_at' => $dateTime,
            'expires_at' => null,
            'access_level' => 'write',
        ];

        $result = $this->metadataMapper->serialize($metadata);

        $decoded = json_decode($result, true);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $decoded['granted_at']);
        $this->assertNull($decoded['expires_at']);
        $this->assertEquals('write', $decoded['access_level']);
    }

    /**
     * Test serializing metadata with nested arrays.
     */
    public function testSerializeMetadataWithNestedArrays(): void
    {
        $metadata = [
            'tags' => ['production', 'critical'],
            'permissions' => [
                'read' => true,
                'write' => true,
                'delete' => false,
            ],
            'scores' => [0.8, 0.9, 0.95],
        ];

        $result = $this->metadataMapper->serialize($metadata);

        $decoded = json_decode($result, true);
        $this->assertEquals($metadata, $decoded);
    }

    /**
     * Test serializing metadata with boolean and numeric values.
     */
    public function testSerializeMetadataWithMixedTypes(): void
    {
        $metadata = [
            'is_active' => true,
            'is_expired' => false,
            'count' => 42,
            'score' => 3.14159,
            'name' => 'test',
            'nullable_field' => null,
        ];

        $result = $this->metadataMapper->serialize($metadata);

        $decoded = json_decode($result, true);
        $this->assertEquals($metadata, $decoded);
    }

    /**
     * Test deserializing valid JSON string.
     */
    public function testDeserializeValidJson(): void
    {
        $json = '{"access_level":"write","granted_by":"admin","confidence_score":0.95}';

        $result = $this->metadataMapper->deserialize($json);

        $expected = [
            'access_level' => 'write',
            'granted_by' => 'admin',
            'confidence_score' => 0.95,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test deserializing empty JSON object.
     */
    public function testDeserializeEmptyJson(): void
    {
        $result = $this->metadataMapper->deserialize('{}');

        $this->assertEquals([], $result);
    }

    /**
     * Test deserializing null value.
     */
    public function testDeserializeNull(): void
    {
        $result = $this->metadataMapper->deserialize(null);

        $this->assertEquals([], $result);
    }

    /**
     * Test deserializing empty string.
     */
    public function testDeserializeEmptyString(): void
    {
        $result = $this->metadataMapper->deserialize('');

        $this->assertEquals([], $result);
    }

    /**
     * Test deserializing invalid JSON throws exception.
     */
    public function testDeserializeInvalidJsonThrowsException(): void
    {
        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to deserialize metadata');

        $this->metadataMapper->deserialize('{"invalid": json}');
    }

    /**
     * Test deserializing JSON with DateTime strings.
     */
    public function testDeserializeJsonWithDateTimeStrings(): void
    {
        $json = '{"granted_at":"2024-01-15T10:30:00+00:00","access_level":"write"}';

        $result = $this->metadataMapper->deserialize($json);

        $this->assertEquals('2024-01-15T10:30:00+00:00', $result['granted_at']);
        $this->assertEquals('write', $result['access_level']);
    }

    /**
     * Test deserializing complex nested JSON.
     */
    public function testDeserializeComplexNestedJson(): void
    {
        $json = '{"tags":["production","critical"],"permissions":{"read":true,"write":true,"delete":false},"scores":[0.8,0.9,0.95]}';

        $result = $this->metadataMapper->deserialize($json);

        $expected = [
            'tags' => ['production', 'critical'],
            'permissions' => [
                'read' => true,
                'write' => true,
                'delete' => false,
            ],
            'scores' => [0.8, 0.9, 0.95],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test round-trip serialization and deserialization.
     */
    public function testRoundTripSerializationDeserialization(): void
    {
        $originalMetadata = [
            'access_level' => 'write',
            'granted_by' => 'admin',
            'granted_at' => new \DateTimeImmutable('2024-01-15T10:30:00Z'),
            'tags' => ['production', 'critical'],
            'permissions' => [
                'read' => true,
                'write' => true,
                'delete' => false,
            ],
            'confidence_score' => 0.95,
            'is_active' => true,
            'nullable_field' => null,
        ];

        // Serialize
        $serialized = $this->metadataMapper->serialize($originalMetadata);

        // Deserialize
        $deserialized = $this->metadataMapper->deserialize($serialized);

        // DateTime objects become strings after serialization
        $expectedAfterRoundTrip = $originalMetadata;
        $expectedAfterRoundTrip['granted_at'] = '2024-01-15T10:30:00+00:00';

        $this->assertEquals($expectedAfterRoundTrip, $deserialized);
    }

    /**
     * Test handling of special characters in metadata.
     */
    public function testHandlingSpecialCharacters(): void
    {
        $metadata = [
            'description' => 'This contains "quotes" and \'apostrophes\'',
            'unicode' => 'Unicode: 🚀 ñáéíóú',
            'newlines' => "Line 1\nLine 2\nLine 3",
            'tabs' => "Column 1\tColumn 2\tColumn 3",
        ];

        $serialized = $this->metadataMapper->serialize($metadata);
        $deserialized = $this->metadataMapper->deserialize($serialized);

        $this->assertEquals($metadata, $deserialized);
    }

    /**
     * Test serializing metadata with objects that can't be serialized.
     */
    public function testSerializeUnsupportedObjectThrowsException(): void
    {
        $metadata = [
            'resource' => fopen('php://memory', 'r'),
        ];

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to serialize metadata');

        $this->metadataMapper->serialize($metadata);

        // Clean up the resource
        if (is_resource($metadata['resource'])) {
            fclose($metadata['resource']);
        }
    }
}
