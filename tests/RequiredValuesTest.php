<?php

namespace GraphQL\Tests;

use GraphQL\SchemaObject\ArgumentsObject;
use GraphQL\SchemaObject\InputObject;
use GraphQL\SchemaObject\MissingRequiredValueException;
use PHPUnit\Framework\TestCase;

class RequiredValuesTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaObject\ArgumentsObject::toArray
     * @covers \GraphQL\SchemaObject\MissingRequiredValueException::assertSet
     */
    public function testMissingRequiredArgumentThrows()
    {
        $this->expectException(MissingRequiredValueException::class);
        $this->expectExceptionMessage(RequiredArgumentsObject::class . ': missing required arguments "id", "handle"');
        (new RequiredArgumentsObject())->setFirst(5)->toArray();
    }

    /**
     * @covers \GraphQL\SchemaObject\ArgumentsObject::toArray
     */
    public function testRequiredArgumentsSetOrBoundToVariables()
    {
        $this->assertEquals(
            ['id' => 'gid://1', 'handle' => '$handle'],
            array_map('strval', (new RequiredArgumentsObject())->setId('gid://1')->useVariable('handle')->toArray())
        );
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testMissingRequiredInputFieldThrowsWhenSerialized()
    {
        $this->expectException(MissingRequiredValueException::class);
        $this->expectExceptionMessage(RequiredInputObject::class . ': missing required field "title"');
        (string) (new RequiredInputObject())->setNote('x');
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::toArray
     */
    public function testMissingRequiredInputFieldThrowsWhenSentAsVariable()
    {
        $this->expectException(MissingRequiredValueException::class);
        (new RequiredInputObject())->toArray();
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testFalsyRequiredValueCountsAsSet()
    {
        $this->assertEquals('{title: ""}', (string) (new RequiredInputObject())->setTitle(''));
    }
}

class RequiredArgumentsObject extends ArgumentsObject
{
    const REQUIRED = ['id', 'handle'];

    protected $id;
    protected $handle;
    protected $first;

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function setFirst($first)
    {
        $this->first = $first;

        return $this;
    }
}

class RequiredInputObject extends InputObject
{
    const REQUIRED = ['title'];

    protected $title;
    protected $note;

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function setNote($note)
    {
        $this->note = $note;

        return $this;
    }
}
