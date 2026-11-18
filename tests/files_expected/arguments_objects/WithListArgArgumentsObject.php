<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class WithListArgArgumentsObject extends ArgumentsObject
{
    protected $listProperty;

    public function setListProperty(array $listProperty): static
    {
        $this->listProperty = $listProperty;

        return $this;
    }
}
