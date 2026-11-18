<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;

class WithInputObjectValueInputObject extends InputObject
{
    protected $inputObject;

    public function setInputObject(WithListValueInputObject $withListValueInputObject): static
    {
        $this->inputObject = $withListValueInputObject;

        return $this;
    }
}
