<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InputObject;

class _TestFilterInputObject extends InputObject
{
    protected $first_name;
    protected $lastName;
    protected $ids;
    protected $testFilter;

    public function setFirstName($firstName): static
    {
        $this->first_name = $firstName;

        return $this;
    }

    public function setLastName($lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function setIds(array $ids): static
    {
        $this->ids = $ids;

        return $this;
    }

    public function setTestFilter(_TestFilterInputObject $testFilterInputObject): static
    {
        $this->testFilter = $testFilterInputObject;

        return $this;
    }
}
