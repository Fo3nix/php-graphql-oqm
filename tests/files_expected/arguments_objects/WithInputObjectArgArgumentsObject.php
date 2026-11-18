<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class WithInputObjectArgArgumentsObject extends ArgumentsObject
{
    protected $objectProperty;

    public function setObjectProperty(SomeInputObject $someInputObject): static
    {
        $this->objectProperty = $someInputObject;

        return $this;
    }
}
