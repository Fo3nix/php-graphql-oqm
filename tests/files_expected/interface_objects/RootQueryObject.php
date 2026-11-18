<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\QueryObject;

class RootQueryObject extends QueryObject
{
    const OBJECT_NAME = "";

    public function selectNode(RootNodeArgumentsObject $argsObject): NodeInterfaceObject
    {
        $object = new NodeInterfaceObject("node");
        $object->appendArguments($argsObject->toArray());
        $this->selectField($object);

        return $object;
    }
}
