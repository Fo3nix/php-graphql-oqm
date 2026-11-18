<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class WithMultipleInputObjectArgsArgumentsObject extends ArgumentsObject
{
    protected $objectProperty;
    protected $another_object_property;

    public function setObjectProperty(SomeInputObject $someInputObject): static
    {
        $this->objectProperty = $someInputObject;

        return $this;
    }

    public function setAnotherObjectProperty(AnotherInputObject $anotherInputObject): static
    {
        $this->another_object_property = $anotherInputObject;

        return $this;
    }
}
