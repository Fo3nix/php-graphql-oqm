<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class WithMultipleScalarArgsArgumentsObject extends ArgumentsObject
{
    protected $scalarProperty;
    protected $another_scalar_property;

    public function setScalarProperty($scalarProperty): static
    {
        $this->scalarProperty = $scalarProperty;

        return $this;
    }

    public function setAnotherScalarProperty($anotherScalarProperty): static
    {
        $this->another_scalar_property = $anotherScalarProperty;

        return $this;
    }
}
