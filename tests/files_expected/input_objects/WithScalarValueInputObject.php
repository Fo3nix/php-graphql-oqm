<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;

class WithScalarValueInputObject extends InputObject
{
    protected $valOne;

    public function setValOne($valOne): static
    {
        $this->valOne = $valOne;

        return $this;
    }
}
