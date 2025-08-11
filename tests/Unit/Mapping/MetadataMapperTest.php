<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Mapping;

use EdgeBinder\Adapter\Weaviate\Exception\WeaviateException;
use EdgeBinder\Adapter\Weaviate\Mapping\MetadataMapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MetadataMapper class.
 */
final class MetadataMapperTest extends TestCase
{
    private MetadataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MetadataMapper();
    }

    public function testSerializeEmptyArray(): void
    {
        $result = $this->mapper->serialize([]);
        
        $this->assertSame('{}', $result);
    }

    public function testSerializeSimpleArray(): void
    {
        $metadata = [
            'level' => 'read',
            'priority' => 1,
            'active' => true,
        ];
        
        $result = $this->mapper->serialize($metadata);
        $decoded = json_decode($result, true);
        
        $this->assertSame($metadata, $decoded);
    }

    public function testSerializeWithDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $metadata = [
            'createdAt' => $dateTime,
            'level' => 'write',
        ];
        
        $result = $this->mapper->serialize($metadata);
        $decoded = json_decode($result, true);
        
        $this->assertSame('2024-01-01T12:00:00+00:00', $decoded['createdAt']);
        $this->assertSame('write', $decoded['level']);
    }

    public function testSerializeWithNestedArrays(): void
    {
        $metadata = [
            'user' => [
                'id' => 123,
                'permissions' => ['read', 'write'],
                'profile' => [
                    'name' => 'John Doe',
                    'active' => true,
                ],
            ],
        ];
        
        $result = $this->mapper->serialize($metadata);
        $decoded = json_decode($result, true);
        
        $this->assertSame($metadata, $decoded);
    }

    public function testSerializeWithNestedDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01T12:00:00+00:00');
        $metadata = [
            'audit' => [
                'createdAt' => $dateTime,
                'level' => 'info',
            ],
        ];
        
        $result = $this->mapper->serialize($metadata);
        $decoded = json_decode($result, true);
        
        $this->assertSame('2024-01-01T12:00:00+00:00', $decoded['audit']['createdAt']);
        $this->assertSame('info', $decoded['audit']['level']);
    }

    public function testSerializeWithMixedTypes(): void
    {
        $metadata = [
            'string' => 'test',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ];
        
        $result = $this->mapper->serialize($metadata);
        $decoded = json_decode($result, true);
        
        $this->assertSame($metadata, $decoded);
    }

    public function testSerializeThrowsExceptionOnInvalidData(): void
    {
        // Create a resource that cannot be JSON encoded
        $resource = fopen('php://memory', 'r');
        if ($resource === false) {
            $this->fail('Failed to create test resource');
        }

        $metadata = ['resource' => $resource];

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to serialize metadata');

        try {
            $this->mapper->serialize($metadata);
        } finally {
            fclose($resource);
        }
    }

    public function testDeserializeNull(): void
    {
        $result = $this->mapper->deserialize(null);
        
        $this->assertSame([], $result);
    }

    public function testDeserializeEmptyString(): void
    {
        $result = $this->mapper->deserialize('');
        
        $this->assertSame([], $result);
    }

    public function testDeserializeSimpleJson(): void
    {
        $json = '{"level":"read","priority":1,"active":true}';
        $result = $this->mapper->deserialize($json);
        
        $expected = [
            'level' => 'read',
            'priority' => 1,
            'active' => true,
        ];
        
        $this->assertSame($expected, $result);
    }

    public function testDeserializeWithDateTime(): void
    {
        $json = '{"createdAt":"2024-01-01T12:00:00+00:00","level":"write"}';
        $result = $this->mapper->deserialize($json);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['createdAt']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $result['createdAt']->format('c'));
        $this->assertSame('write', $result['level']);
    }

    public function testDeserializeWithNestedDateTime(): void
    {
        $json = '{"audit":{"createdAt":"2024-01-01T12:00:00+00:00","level":"info"}}';
        $result = $this->mapper->deserialize($json);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['audit']['createdAt']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $result['audit']['createdAt']->format('c'));
        $this->assertSame('info', $result['audit']['level']);
    }

    public function testDeserializeWithInvalidDateTime(): void
    {
        // This looks like ISO 8601 but is invalid
        $json = '{"createdAt":"2024-13-01T25:00:00+00:00","level":"write"}';
        $result = $this->mapper->deserialize($json);
        
        // Should return the original string when DateTime parsing fails
        $this->assertSame('2024-13-01T25:00:00+00:00', $result['createdAt']);
        $this->assertSame('write', $result['level']);
    }

    public function testDeserializeThrowsExceptionOnInvalidJson(): void
    {
        $invalidJson = '{"invalid": json}';
        
        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to deserialize metadata');
        
        $this->mapper->deserialize($invalidJson);
    }

    public function testDeserializeThrowsExceptionOnNonArrayJson(): void
    {
        $nonArrayJson = '"this is a string"';
        
        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to deserialize metadata');
        $this->expectExceptionMessage('Decoded JSON is not an array');
        
        $this->mapper->deserialize($nonArrayJson);
    }

    public function testRoundTripSerialization(): void
    {
        $originalMetadata = [
            'level' => 'write',
            'createdAt' => new \DateTimeImmutable('2024-01-01T12:00:00+00:00'),
            'user' => [
                'id' => 123,
                'lastLogin' => new \DateTime('2024-01-02T10:30:00+00:00'),
                'permissions' => ['read', 'write', 'admin'],
            ],
            'flags' => [
                'active' => true,
                'verified' => false,
            ],
        ];
        
        $serialized = $this->mapper->serialize($originalMetadata);
        $deserialized = $this->mapper->deserialize($serialized);
        
        // Check basic structure
        $this->assertSame('write', $deserialized['level']);
        $this->assertSame(123, $deserialized['user']['id']);
        $this->assertSame(['read', 'write', 'admin'], $deserialized['user']['permissions']);
        $this->assertTrue($deserialized['flags']['active']);
        $this->assertFalse($deserialized['flags']['verified']);
        
        // Check DateTime objects
        $this->assertInstanceOf(\DateTimeImmutable::class, $deserialized['createdAt']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $deserialized['user']['lastLogin']);
        $this->assertSame('2024-01-01T12:00:00+00:00', $deserialized['createdAt']->format('c'));
        $this->assertSame('2024-01-02T10:30:00+00:00', $deserialized['user']['lastLogin']->format('c'));
    }

    public function testIso8601DateTimeDetection(): void
    {
        // Test various ISO 8601 formats that should be detected
        $validDates = [
            '2024-01-01T12:00:00+00:00',
            '2024-12-31T23:59:59-05:00',
            '2024-06-15T14:30:45Z',
        ];
        
        foreach ($validDates as $dateString) {
            $json = json_encode(['date' => $dateString]);
            $this->assertNotFalse($json, 'Failed to encode test data');

            $result = $this->mapper->deserialize($json);

            $this->assertInstanceOf(\DateTimeImmutable::class, $result['date'],
                "Failed to detect ISO 8601 format: {$dateString}");
        }
    }

    public function testNonIso8601StringsNotConverted(): void
    {
        $nonDateStrings = [
            'not-a-date',
            '2024-01-01',  // Date only, no time
            '12:00:00',    // Time only, no date
            '2024/01/01 12:00:00',  // Wrong format
            'Mon, 01 Jan 2024 12:00:00 GMT',  // RFC format
        ];
        
        foreach ($nonDateStrings as $string) {
            $json = json_encode(['value' => $string]);
            $this->assertNotFalse($json, 'Failed to encode test data');

            $result = $this->mapper->deserialize($json);

            $this->assertSame($string, $result['value'],
                "Incorrectly converted non-ISO 8601 string: {$string}");
        }
    }

    public function testSerializePreservesZeroFraction(): void
    {
        $metadata = ['value' => 1.0];
        $result = $this->mapper->serialize($metadata);

        // Should preserve the .0 for floats
        $this->assertStringContainsString('1.0', $result);
    }

    public function testDeserializeHandlesDeepNesting(): void
    {
        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'date' => new \DateTimeImmutable('2024-01-01T12:00:00+00:00'),
                            'value' => 'deep',
                        ],
                    ],
                ],
            ],
        ];
        
        $serialized = $this->mapper->serialize($deepMetadata);
        $deserialized = $this->mapper->deserialize($serialized);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, 
            $deserialized['level1']['level2']['level3']['level4']['date']);
        $this->assertSame('deep', 
            $deserialized['level1']['level2']['level3']['level4']['value']);
    }
}
