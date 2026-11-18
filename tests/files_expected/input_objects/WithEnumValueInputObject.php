<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;
use GraphQL\RawObject;

class WithEnumValueInputObject extends InputObject
{
    const REQUIRED = ["enumVal"];

    protected $enumVal;

    public function setEnumVal($some): static
    {
        $this->enumVal = new RawObject($some);

        return $this;
    }
}
