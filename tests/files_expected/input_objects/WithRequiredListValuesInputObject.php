<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;

class WithRequiredListValuesInputObject extends InputObject
{
    const REQUIRED = ["list_two"];

    protected $listOne;
    protected $list_two;

    public function setListOne(array $listOne): static
    {
        $this->listOne = $listOne;

        return $this;
    }

    public function setListTwo(array $listTwo): static
    {
        $this->list_two = $listTwo;

        return $this;
    }
}
