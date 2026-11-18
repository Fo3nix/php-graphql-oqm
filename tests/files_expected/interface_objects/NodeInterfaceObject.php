<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\InterfaceObject;

class NodeInterfaceObject extends InterfaceObject
{
    const OBJECT_NAME = "Node";

    public function selectId(): static
    {
        $this->selectField("id");

        return $this;
    }

    public function onProduct(): ProductQueryObject
    {
        $object = new ProductQueryObject();
        $this->addPossibleType($object);

        return $object;
    }

    public function onCollection(): CollectionQueryObject
    {
        $object = new CollectionQueryObject();
        $this->addPossibleType($object);

        return $object;
    }
}
