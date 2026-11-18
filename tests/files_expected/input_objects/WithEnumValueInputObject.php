<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;
use GraphQL\RawObject;

class WithEnumValueInputObject extends InputObject
{
    protected $enumVal;

    public function setEnumVal($some)
    {
        $this->enumVal = new RawObject($some);

        return $this;
    }
}
