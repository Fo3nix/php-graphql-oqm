<?php

namespace GraphQL\Tests\SchemaObject;

interface Node
{
    
    /**
     * @return string
     */
    public function getId(): ?string;

    /**
     * Converts this object to an array.
     * @return array
     */
    public function asArray(): array;
}
