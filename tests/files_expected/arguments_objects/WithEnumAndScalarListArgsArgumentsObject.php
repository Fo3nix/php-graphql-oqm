<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;
use GraphQL\RawObject;

class WithEnumAndScalarListArgsArgumentsObject extends ArgumentsObject
{
    const REQUIRED = ["another_list_property"];

    protected $listProperty;
    protected $another_list_property;

    public function setListProperty(array $listProperty)
    {
        $this->listProperty = array_map(function ($value) {
            return new RawObject($value);
        }, $listProperty);

        return $this;
    }

    public function setAnotherListProperty(array $anotherListProperty)
    {
        $this->another_list_property = $anotherListProperty;

        return $this;
    }
}
