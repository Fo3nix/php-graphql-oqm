<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\QueryObject;

class FieldCollisionQueryObject extends QueryObject
{
    const OBJECT_NAME = "FieldCollision";

    public function selectField_(): static
    {
        $this->selectField("field");

        return $this;
    }

    public function selectTypename_(): static
    {
        $this->selectField("typename");

        return $this;
    }
}
