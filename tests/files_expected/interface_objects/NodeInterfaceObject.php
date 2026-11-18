<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InterfaceObject;

class NodeInterfaceObject extends InterfaceObject
{
    const OBJECT_NAME = "Node";

    public function selectId()
    {
        $this->selectField("id");

        return $this;
    }

    public function onProduct()
    {
        $object = new ProductQueryObject();
        $this->addPossibleType($object);

        return $object;
    }

    public function onCollection()
    {
        $object = new CollectionQueryObject();
        $this->addPossibleType($object);

        return $object;
    }
}
