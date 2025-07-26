<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Example unit test to demonstrate the testing structure.
 * This will be replaced with actual adapter tests once implementation begins.
 */
final class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        $this->assertTrue(true, 'This is a placeholder test');
    }

    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual(
            '8.3.0',
            PHP_VERSION,
            'PHP version must be 8.3 or higher'
        );
    }

    public function testComposerAutoload(): void
    {
        $this->assertTrue(
            class_exists('Weaviate\WeaviateClient'),
            'Weaviate client should be available via autoload'
        );
    }
}
