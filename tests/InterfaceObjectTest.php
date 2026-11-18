<?php

namespace GraphQL\Tests;

use GraphQL\SchemaObject\ArgumentsObject;
use GraphQL\SchemaObject\InterfaceObject;
use PHPUnit\Framework\TestCase;

/**
 * Class InterfaceObjectTest
 *
 * @package GraphQL\Tests
 */
class InterfaceObjectTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaObject\AbstractTypeObject::__construct
     */
    public function testTypenameIsAlwaysSelected()
    {
        $object = new SimpleNodeInterfaceObject('node');
        $this->assertEquals(
            'query {
node {
__typename
}
}',
            (string) $object->getQuery()
        );
    }

    /**
     * @covers \GraphQL\SchemaObject\AbstractTypeObject::addPossibleType
     */
    public function testSharedFieldsAndInlineFragments()
    {
        $object = new SimpleNodeInterfaceObject('node');
        $object->selectId();
        $object->onType1()->selectScalar();
        $object->onType2()->selectAnotherScalar();
        $this->assertEquals(
            'query {
node {
__typename
id
... on Type1 {
scalar
}
... on Type2 {
anotherScalar
}
}
}',
            (string) $object->getQuery()
        );
    }

    /**
     * @covers \GraphQL\SchemaObject\AbstractTypeObject::addPossibleType
     */
    public function testInterfaceFieldNestedWithArguments()
    {
        $root = new SimpleInterfaceRootQueryObject();
        $root->selectNode((new SimpleRootNodeArgumentsObject())->setId('gid://1'))
            ->onType1()
            ->selectScalar();
        $this->assertEquals(
            'query {
node(id: "gid://1") {
__typename
... on Type1 {
scalar
}
}
}',
            (string) $root->getQuery()
        );
    }
}

class SimpleNodeInterfaceObject extends InterfaceObject
{
    const OBJECT_NAME = 'Node';

    public function selectId()
    {
        $this->selectField('id');

        return $this;
    }

    public function onType1()
    {
        $object = new NodeType1QueryObject();
        $this->addPossibleType($object);

        return $object;
    }

    public function onType2()
    {
        $object = new NodeType2QueryObject();
        $this->addPossibleType($object);

        return $object;
    }
}

class SimpleInterfaceRootQueryObject extends \GraphQL\SchemaObject\QueryObject
{
    const OBJECT_NAME = '';

    public function selectNode(SimpleRootNodeArgumentsObject $argsObject = null)
    {
        $object = new SimpleNodeInterfaceObject('node');
        if ($argsObject !== null) {
            $object->appendArguments($argsObject->toArray());
        }
        $this->selectField($object);

        return $object;
    }
}

class SimpleRootNodeArgumentsObject extends ArgumentsObject
{
    protected $id;

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
}

class NodeType1QueryObject extends \GraphQL\SchemaObject\QueryObject
{
    const OBJECT_NAME = 'Type1';

    public function selectScalar()
    {
        $this->selectField('scalar');

        return $this;
    }
}

class NodeType2QueryObject extends \GraphQL\SchemaObject\QueryObject
{
    const OBJECT_NAME = 'Type2';

    public function selectAnotherScalar()
    {
        $this->selectField('anotherScalar');

        return $this;
    }
}
