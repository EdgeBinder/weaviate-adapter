<?php

class SimpleEntityCriteria
{
    public function __construct(
        public readonly string $type,
        public readonly string $id
    ) {}
    
    public function transform(): string
    {
        return "transformed: {$this->type} {$this->id}";
    }
}

$entity = new SimpleEntityCriteria('User', 'user123');
echo 'Transform method exists: ';
var_dump(method_exists($entity, 'transform'));
echo 'Transform result: ' . $entity->transform() . PHP_EOL;
