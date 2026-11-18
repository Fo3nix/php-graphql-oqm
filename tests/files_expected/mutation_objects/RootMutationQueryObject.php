<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\MutationObject;

class RootMutationQueryObject extends MutationObject
{
    const OBJECT_NAME = "";

    public function selectCreateThing(RootMutationCreateThingArgumentsObject $argsObject)
    {
        $object = new CreateThingPayloadQueryObject("createThing");
        $object->appendArguments($argsObject->toArray());
        $this->selectField($object);

        return $object;
    }
}
