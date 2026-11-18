<?php

namespace GraphQL\Tests;

require_once 'files_expected/input_objects/_TestFilterInputObject.php';

use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\SchemaObject\InputObject;
use GraphQL\Tests\SchemaObject\_TestFilterInputObject;
use PHPUnit\Framework\TestCase;

/**
 * Class InputObjectTest
 */
class InputObjectTest extends TestCase
{
    /**
     * @var _TestFilterInputObject
     */
    protected $inputObject;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->inputObject = new _TestFilterInputObject();
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testEmptyInputObject()
    {
        $this->assertEquals('{}', (string) $this->inputObject);
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::toRawObject
     */
    public function testConvertToRawObject()
    {
        $this->assertEquals('{}', (string) $this->inputObject->toRawObject());
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::toRawObject
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testSetInputValues()
    {
        $this->inputObject->setFirstName('Mostafa');
        $this->assertEquals('{first_name: "Mostafa"}', (string) $this->inputObject);
        $this->assertEquals('{first_name: "Mostafa"}', (string) $this->inputObject->toRawObject());

        $this->inputObject->setLastName('Ghoneimy');
        $this->assertEquals('{first_name: "Mostafa", lastName: "Ghoneimy"}', (string) $this->inputObject);
        $this->assertEquals('{first_name: "Mostafa", lastName: "Ghoneimy"}', (string) $this->inputObject->toRawObject());

        $this->inputObject->setIds([2, 34, 567]);
        $this->assertEquals('{first_name: "Mostafa", lastName: "Ghoneimy", ids: [2, 34, 567]}', (string) $this->inputObject);
        $this->assertEquals('{first_name: "Mostafa", lastName: "Ghoneimy", ids: [2, 34, 567]}', (string) $this->inputObject->toRawObject());
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testFalsyValuesAreKeptAndNullIsOmitted()
    {
        $input = (new SerializationInputObject())
            ->setPublished(false)
            ->setQuantity(0)
            ->setNote('')
            ->setTags([]);

        $this->assertEquals('{published: false, quantity: 0, note: "", tags: []}', (string) $input);
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testEnumValuesAreWrittenUnquoted()
    {
        $input = (new SerializationInputObject())
            ->setStatus('ACTIVE')
            ->setStatuses(['ACTIVE', 'DRAFT'])
            ->setTags(['ACTIVE']);

        $this->assertEquals('{tags: ["ACTIVE"], status: ACTIVE, statuses: [ACTIVE, DRAFT]}', (string) $input);
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::__toString
     */
    public function testNestedInputObjectsInListsAreSerialized()
    {
        $query = (new Query('productsUpdate'))
            ->setArguments(['input' => [
                (new SerializationInputObject())->setStatus('DRAFT'),
                (new SerializationInputObject())->setQuantity(0),
            ]])
            ->setSelectionSet(['id']);

        $this->assertEquals('query {
productsUpdate(input: [{status: DRAFT}, {quantity: 0}]) {
id
}
}', (string) $query);
    }
}

/**
 * Mirrors the setters the generator creates for scalar, list, enum and enum list values
 */
class SerializationInputObject extends InputObject
{
    protected $published;
    protected $quantity;
    protected $note;
    protected $tags;
    protected $status;
    protected $statuses;

    public function setPublished($published)
    {
        $this->published = $published;

        return $this;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function setNote($note)
    {
        $this->note = $note;

        return $this;
    }

    public function setTags(array $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    public function setStatus($status)
    {
        $this->status = new RawObject($status);

        return $this;
    }

    public function setStatuses(array $statuses)
    {
        $this->statuses = array_map(function ($value) {
            return new RawObject($value);
        }, $statuses);

        return $this;
    }
}
