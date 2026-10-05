<?php

namespace GraphQL\Tests;

use GraphQL\Client;
use GraphQL\SchemaGenerator\SchemaInspector;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SchemaInspectorTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getObjectSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::buildTypeSubQuery
     */
    public function testObjectSchemaQueryNestsTypeDeeplyEnoughForWrappedTypes()
    {
        $mockHandler = new MockHandler();
        $handler = HandlerStack::create($mockHandler);
        $schemaInspector = new SchemaInspector(new Client('', [], ['handler' => $handler]));

        $mockHandler->append(new Response(200, [], json_encode([
            'data' => [
                '__type' => [
                    'name' => 'ShopifyqlRowMetadata',
                    'kind' => 'OBJECT',
                    'fields' => [],
                ]
            ]
        ])));

        $schemaInspector->getObjectSchema('ShopifyqlRowMetadata');

        $request = $mockHandler->getLastRequest();
        $body = json_decode((string) $request->getBody(), true);

        $this->assertGreaterThanOrEqual(
            6,
            substr_count($body['query'], 'ofType'),
            'The type sub query must request at least 6 ofType levels to support the 5 wrappers of [[String!]!]!'
        );
    }
}
