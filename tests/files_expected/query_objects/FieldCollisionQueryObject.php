<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\QueryObject;

class FieldCollisionQueryObject extends QueryObject
{
    const OBJECT_NAME = "FieldCollision";

    public function selectField_()
    {
        $this->selectField("field");

        return $this;
    }
}
