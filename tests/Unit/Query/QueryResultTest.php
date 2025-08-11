<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit\Query;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryResultInterface;
use EdgeBinder\Query\QueryResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for QueryResult class.
 */
final class QueryResultTest extends TestCase
{
    public function testImplementsQueryResultInterface(): void
    {
        $result = new QueryResult([]);
        
        $this->assertInstanceOf(QueryResultInterface::class, $result);
    }

    public function testConstructorWithEmptyArray(): void
    {
        $result = new QueryResult([]);
        
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame([], $result->getBindings());
    }

    public function testConstructorWithBindings(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $bindings = [$binding1, $binding2];
        
        $result = new QueryResult($bindings);
        
        $this->assertSame($bindings, $result->getBindings());
    }

    public function testGetBindingsReturnsOriginalArray(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $binding3 = $this->createMock(BindingInterface::class);
        $bindings = [$binding1, $binding2, $binding3];
        
        $result = new QueryResult($bindings);
        
        $this->assertSame($bindings, $result->getBindings());
        $this->assertCount(3, $result->getBindings());
    }

    public function testIsEmptyWithEmptyArray(): void
    {
        $result = new QueryResult([]);
        
        $this->assertTrue($result->isEmpty());
    }

    public function testIsEmptyWithNonEmptyArray(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding]);
        
        $this->assertFalse($result->isEmpty());
    }

    public function testFirstWithEmptyArray(): void
    {
        $result = new QueryResult([]);
        
        $this->assertNull($result->first());
    }

    public function testFirstWithSingleBinding(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding]);
        
        $this->assertSame($binding, $result->first());
    }

    public function testFirstWithMultipleBindings(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $binding3 = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding1, $binding2, $binding3]);
        
        $this->assertSame($binding1, $result->first());
    }

    public function testCountWithEmptyArray(): void
    {
        $result = new QueryResult([]);
        
        $this->assertSame(0, $result->count());
    }

    public function testCountWithSingleBinding(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding]);
        
        $this->assertSame(1, $result->count());
    }

    public function testCountWithMultipleBindings(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $binding3 = $this->createMock(BindingInterface::class);
        $binding4 = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding1, $binding2, $binding3, $binding4]);
        
        $this->assertSame(4, $result->count());
    }

    public function testGetIteratorWithEmptyArray(): void
    {
        $result = new QueryResult([]);
        $iterator = $result->getIterator();
        
        $this->assertInstanceOf(\ArrayIterator::class, $iterator);
        $this->assertSame([], $iterator->getArrayCopy());
    }

    public function testGetIteratorWithBindings(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $bindings = [$binding1, $binding2];
        
        $result = new QueryResult($bindings);
        $iterator = $result->getIterator();
        
        $this->assertInstanceOf(\ArrayIterator::class, $iterator);
        $this->assertSame($bindings, $iterator->getArrayCopy());
    }

    public function testIteratorCanBeUsedInForeachLoop(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $binding3 = $this->createMock(BindingInterface::class);
        $bindings = [$binding1, $binding2, $binding3];
        
        $result = new QueryResult($bindings);
        
        $iteratedBindings = [];
        foreach ($result as $binding) {
            $iteratedBindings[] = $binding;
        }
        
        $this->assertSame($bindings, $iteratedBindings);
    }

    public function testReadonlyClassCannotBeModified(): void
    {
        $binding = $this->createMock(BindingInterface::class);
        $result = new QueryResult([$binding]);
        
        // Verify that the class is readonly by checking that properties cannot be modified
        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testBindingsArrayIsNotModifiableFromOutside(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $originalBindings = [$binding1, $binding2];
        
        $result = new QueryResult($originalBindings);
        
        // Modify the original array
        $originalBindings[] = $this->createMock(BindingInterface::class);
        
        // The result should still have the original bindings
        $this->assertCount(2, $result->getBindings());
        $this->assertSame($binding1, $result->getBindings()[0]);
        $this->assertSame($binding2, $result->getBindings()[1]);
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        
        $result1 = new QueryResult([$binding1]);
        $result2 = new QueryResult([$binding2]);
        
        $this->assertNotSame($result1->getBindings(), $result2->getBindings());
        $this->assertSame($binding1, $result1->first());
        $this->assertSame($binding2, $result2->first());
        $this->assertSame(1, $result1->count());
        $this->assertSame(1, $result2->count());
    }

    public function testConsistencyBetweenCountAndIsEmpty(): void
    {
        // Empty result
        $emptyResult = new QueryResult([]);
        $this->assertTrue($emptyResult->isEmpty());
        $this->assertSame(0, $emptyResult->count());
        
        // Non-empty result
        $binding = $this->createMock(BindingInterface::class);
        $nonEmptyResult = new QueryResult([$binding]);
        $this->assertFalse($nonEmptyResult->isEmpty());
        $this->assertSame(1, $nonEmptyResult->count());
    }

    public function testConsistencyBetweenGetBindingsAndIterator(): void
    {
        $binding1 = $this->createMock(BindingInterface::class);
        $binding2 = $this->createMock(BindingInterface::class);
        $bindings = [$binding1, $binding2];
        
        $result = new QueryResult($bindings);
        
        $this->assertSame($bindings, $result->getBindings());
        $this->assertSame($bindings, $result->getIterator()->getArrayCopy());
    }
}
