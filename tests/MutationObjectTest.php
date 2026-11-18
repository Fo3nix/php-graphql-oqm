<?php

namespace GraphQL\Tests;

use GraphQL\Mutation;
use GraphQL\SchemaObject\ArgumentsObject;
use GraphQL\SchemaObject\InputObject;
use GraphQL\SchemaObject\MutationObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MutationObjectTest
 *
 * @package GraphQL\Tests
 */
class MutationObjectTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaObject\MutationObject::__construct
     * @covers \GraphQL\SchemaObject\MutationObject::getMutation
     */
    public function testRootMutationIsSentAsMutationOperation()
    {
        $object = new SimpleRootMutationObject();
        $object->selectCreateThing()->selectId();

        $this->assertInstanceOf(Mutation::class, $object->getMutation());
        $this->assertEquals(
            'mutation {
createThing {
id
}
}',
            (string) $object->getMutation()
        );
        $this->assertSame((string) $object->getMutation(), (string) $object->getQuery());
    }

    /**
     * @covers \GraphQL\SchemaObject\MutationObject::getMutation
     */
    public function testMutationFieldWithInputObjectArgument()
    {
        $object = new SimpleRootMutationObject();
        $object
            ->selectCreateThing(
                (new SimpleRootMutationCreateThingArgumentsObject())
                    ->setInput((new SimpleThingInputObject())->setName('Hat')->setTags(['a', 'b']))
            )
            ->selectId()
            ->selectName();

        $this->assertEquals(
            'mutation {
createThing(input: {name: "Hat", tags: ["a", "b"]}) {
id
name
}
}',
            (string) $object->getMutation()
        );
    }

    /**
     * @covers \GraphQL\SchemaObject\MutationObject::getMutation
     */
    public function testMultipleMutationFields()
    {
        $object = new SimpleRootMutationObject();
        $object->selectCreateThing()->selectId();
        $object->selectDeleteThing((new SimpleRootMutationCreateThingArgumentsObject())->setId('1'))->selectId();

        $this->assertEquals(
            'mutation {
createThing {
id
}
deleteThing(id: "1") {
id
}
}',
            (string) $object->getMutation()
        );
    }
}

class SimpleRootMutationObject extends MutationObject
{
    const OBJECT_NAME = '';

    public function selectCreateThing(SimpleRootMutationCreateThingArgumentsObject $argsObject = null)
    {
        $object = new SimpleThingQueryObject('createThing');
        if ($argsObject !== null) {
            $object->appendArguments($argsObject->toArray());
        }
        $this->selectField($object);

        return $object;
    }

    public function selectDeleteThing(SimpleRootMutationCreateThingArgumentsObject $argsObject = null)
    {
        $object = new SimpleThingQueryObject('deleteThing');
        if ($argsObject !== null) {
            $object->appendArguments($argsObject->toArray());
        }
        $this->selectField($object);

        return $object;
    }
}

class SimpleThingQueryObject extends \GraphQL\SchemaObject\QueryObject
{
    const OBJECT_NAME = 'Thing';

    public function selectId()
    {
        $this->selectField('id');

        return $this;
    }

    public function selectName()
    {
        $this->selectField('name');

        return $this;
    }
}

class SimpleRootMutationCreateThingArgumentsObject extends ArgumentsObject
{
    protected $id;
    protected $input;

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setInput(SimpleThingInputObject $input)
    {
        $this->input = $input;
        return $this;
    }
}

class SimpleThingInputObject extends InputObject
{
    protected $name;
    protected $tags;

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function setTags(array $tags)
    {
        $this->tags = $tags;
        return $this;
    }
}
