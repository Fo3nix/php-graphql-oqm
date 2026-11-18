<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\UnionObject;

class UnionTestObjectUnionObject extends UnionObject
{
    public function onUnionObject1(): UnionObject1QueryObject
    {
        $object = new UnionObject1QueryObject();
        $this->addPossibleType($object);

        return $object;
    }

    public function onUnionObject2(): UnionObject2QueryObject
    {
        $object = new UnionObject2QueryObject();
        $this->addPossibleType($object);

        return $object;
    }
}
