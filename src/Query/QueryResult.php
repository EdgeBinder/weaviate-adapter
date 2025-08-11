<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Query;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\QueryResultInterface;

/**
 * Default implementation of query results.
 *
 * Wraps an array of bindings and provides convenient access methods.
 */
readonly class QueryResult implements QueryResultInterface
{
    /**
     * @param BindingInterface[] $bindings
     */
    public function __construct(
        private array $bindings
    ) {
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function isEmpty(): bool
    {
        return empty($this->bindings);
    }

    public function first(): ?BindingInterface
    {
        return $this->bindings[0] ?? null;
    }

    public function count(): int
    {
        return count($this->bindings);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->bindings);
    }
}
