<?php

namespace GraphQL\Tests;

require_once __DIR__ . '/MutationObjectTest.php';
require_once __DIR__ . '/InputObjectTest.php';

use GraphQL\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Uses the mutation test objects from MutationObjectTest and the input object from InputObjectTest
 */
class VariablesTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaObject\QueryObject::setVariable
     * @covers \GraphQL\SchemaObject\ArgumentsObject::useVariable
     * @covers \GraphQL\SchemaObject\InputObject::toArray
     */
    public function testMutationSendsInputAsVariable()
    {
        $mutation = (new SimpleRootMutationObject())
            ->setOperationName('CreateThing')
            ->setVariable('input', 'ThingInput', true);
        $mutation->selectCreateThing((new SimpleRootMutationCreateThingArgumentsObject())->useVariable('input'))->selectId();

        $this->assertEquals(
            'mutation CreateThing($input: ThingInput!) {
createThing(input: $input) {
id
}
}',
            (string) $mutation->getMutation()
        );

        $mockHandler = new MockHandler([new Response(200, [], json_encode(['data' => ['createThing' => ['id' => '1']]]))]);
        $client = new Client('', [], ['handler' => HandlerStack::create($mockHandler)]);
        $input = (new SimpleThingInputObject())->setName('Hat "with" \\ quotes')->setTags(['a', 'b']);
        $client->runQuery($mutation->getMutation(), true, ['input' => $input->toArray()]);

        $body = json_decode((string) $mockHandler->getLastRequest()->getBody(), true);
        $this->assertSame((string) $mutation->getMutation(), $body['query']);
        $this->assertSame(['input' => ['name' => 'Hat "with" \\ quotes', 'tags' => ['a', 'b']]], $body['variables']);
    }

    /**
     * @covers \GraphQL\SchemaObject\ArgumentsObject::useVariable
     * @covers \GraphQL\SchemaObject\VariableReference::create
     */
    public function testArgumentVariableWithDifferentName()
    {
        $mutation = (new SimpleRootMutationObject())->setVariable('thingId', 'ID', true);
        $mutation->selectDeleteThing((new SimpleRootMutationCreateThingArgumentsObject())->useVariable('id', 'thingId'))->selectId();

        $this->assertEquals(
            'mutation($thingId: ID!) {
deleteThing(id: $thingId) {
id
}
}',
            (string) $mutation->getMutation()
        );
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::useVariable
     */
    public function testInputFieldVariable()
    {
        $input = (new SerializationInputObject())->setQuantity(1)->useVariable('status', 'newStatus');
        $this->assertEquals('{quantity: 1, status: $newStatus}', (string) $input);
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::toArray
     */
    public function testInputToVariableValue()
    {
        $nested = (new SerializationInputObject())->setStatus('DRAFT');
        $input = (new SerializationInputObject())
            ->setPublished(false)
            ->setQuantity(0)
            ->setStatus('ACTIVE')
            ->setStatuses(['ACTIVE', 'DRAFT'])
            ->setTags([$nested]);

        $this->assertSame([
            'published' => false,
            'quantity' => 0,
            'tags' => [['status' => 'DRAFT']],
            'status' => 'ACTIVE',
            'statuses' => ['ACTIVE', 'DRAFT'],
        ], $input->toArray());
    }

    /**
     * @covers \GraphQL\SchemaObject\VariableReference::create
     */
    public function testUnknownArgumentIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no argument or field "nope"');
        (new SimpleRootMutationCreateThingArgumentsObject())->useVariable('nope');
    }

    /**
     * @covers \GraphQL\SchemaObject\VariableReference::create
     */
    public function testInvalidVariableNameIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SimpleRootMutationCreateThingArgumentsObject())->useVariable('id', '$id');
    }

    /**
     * @covers \GraphQL\SchemaObject\InputObject::toArray
     */
    public function testVariableReferenceCannotBeSentAsValue()
    {
        $this->expectException(\LogicException::class);
        (new SerializationInputObject())->useVariable('status')->toArray();
    }
}
