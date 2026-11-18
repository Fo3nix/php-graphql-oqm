<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\QueryObject;

class MultipleObjectSelectorsQueryObject extends QueryObject
{
    const OBJECT_NAME = "MultipleObjectSelectors";

    public function selectRight(?MultipleObjectSelectorsRightArgumentsObject $argsObject = null): MultipleObjectSelectorsRightQueryObject
    {
        $object = new MultipleObjectSelectorsRightQueryObject("right");
        if ($argsObject !== null) {
            $object->appendArguments($argsObject->toArray());
        }
        $this->selectField($object);

        return $object;
    }

    /**
     * @deprecated
     */
    public function selectLeftObjects(?MultipleObjectSelectorsLeftObjectsArgumentsObject $argsObject = null): LeftQueryObject
    {
        $object = new LeftQueryObject("left_objects");
        if ($argsObject !== null) {
            $object->appendArguments($argsObject->toArray());
        }
        $this->selectField($object);

        return $object;
    }
}
