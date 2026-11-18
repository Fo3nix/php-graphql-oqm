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
        $schemaInspector = $this->createInspector($mockHandler, false);

        $mockHandler->append($this->createTypeResponse($this->createType('ShopifyqlRowMetadata', 'OBJECT')));

        $schemaInspector->getObjectSchema('ShopifyqlRowMetadata');

        $request = $mockHandler->getLastRequest();
        $body = json_decode((string) $request->getBody(), true);

        $this->assertGreaterThanOrEqual(
            6,
            substr_count($body['query'], 'ofType'),
            'The type sub query must request at least 6 ofType levels to support the 5 wrappers of [[String!]!]!'
        );
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getQueryTypeSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getObjectSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getEnumObjectSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::prefetchFullSchema
     */
    public function testPrefetchedSchemaServesLookupsFromSingleRequest()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT', [
                'fields' => [[
                    'name' => 'product',
                    'isDeprecated' => false,
                    'deprecationReason' => null,
                    'type' => ['name' => 'Product', 'kind' => 'OBJECT', 'ofType' => null],
                    'args' => [],
                ]],
            ]),
            $this->createType('Product', 'OBJECT'),
            $this->createType('SomeEnum', 'ENUM', [
                'enumValues' => [['name' => 'ONE']],
            ]),
        ]));

        $this->assertSame('Root', $schemaInspector->getQueryTypeSchema()['name']);
        $this->assertSame('Product', $schemaInspector->getObjectSchema('Product')['name']);
        $this->assertSame('SomeEnum', $schemaInspector->getEnumObjectSchema('SomeEnum')['name']);
        $this->assertSame(0, $mockHandler->count(), 'All lookups must be served from the single prefetched response');
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getMutationTypeSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::prefetchFullSchema
     */
    public function testPrefetchedSchemaServesMutationType()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT'),
            $this->createType('Mutation', 'OBJECT'),
        ], 'Root', 'Mutation'));

        $this->assertSame('Root', $schemaInspector->getQueryTypeSchema()['name']);
        $this->assertSame('Mutation', $schemaInspector->getMutationTypeSchema()['name']);
        $this->assertSame(0, $mockHandler->count(), 'The mutation type must be served from the single prefetched response');

        $body = json_decode((string) $mockHandler->getLastRequest()->getBody(), true);
        $this->assertStringContainsString('mutationType', $body['query']);
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getMutationTypeSchema
     */
    public function testMutationTypeIsNullWhenSchemaHasNoMutations()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT'),
        ]));

        $this->assertNull($schemaInspector->getMutationTypeSchema());
        $this->assertSame(0, $mockHandler->count());
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getMutationTypeSchema
     */
    public function testFetchesMutationTypeDirectlyWhenPrefetchIsDisabled()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler, false);

        $mockHandler->append(new Response(200, [], json_encode([
            'data' => ['__schema' => ['mutationType' => $this->createType('Mutation', 'OBJECT')]],
        ])));
        $mockHandler->append(new Response(200, [], json_encode([
            'data' => ['__schema' => ['mutationType' => null]],
        ])));

        $this->assertSame('Mutation', $schemaInspector->getMutationTypeSchema()['name']);
        $this->assertNull($schemaInspector->getMutationTypeSchema());
        $this->assertSame(0, $mockHandler->count());
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getInterfaceObjectSchema
     */
    public function testPrefetchedSchemaServesInterfaceType()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT'),
            $this->createType('Node', 'INTERFACE', [
                'possibleTypes' => [['kind' => 'OBJECT', 'name' => 'Product']],
            ]),
        ]));

        $interface = $schemaInspector->getInterfaceObjectSchema('Node');
        $this->assertSame('Node', $interface['name']);
        $this->assertSame('Product', $interface['possibleTypes'][0]['name']);
        $this->assertSame(0, $mockHandler->count());
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getInterfaceObjectSchema
     */
    public function testFetchesInterfaceFieldsAndPossibleTypesWhenPrefetchIsDisabled()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler, false);

        $mockHandler->append($this->createTypeResponse($this->createType('Node', 'INTERFACE', [
            'possibleTypes' => [['kind' => 'OBJECT', 'name' => 'Product']],
        ])));

        $this->assertSame('Node', $schemaInspector->getInterfaceObjectSchema('Node')['name']);

        $body = json_decode((string) $mockHandler->getLastRequest()->getBody(), true);
        $this->assertStringContainsString('fields(includeDeprecated: true)', $body['query']);
        $this->assertStringContainsString('possibleTypes', $body['query']);
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::prefetchFullSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getObjectSchema
     */
    public function testFallsBackToPerTypeQueriesWhenPrefetchFails()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createTypeResponse($this->createType('Root', 'OBJECT')));
        $mockHandler->append($this->createTypeResponse($this->createType('Product', 'OBJECT')));

        $this->assertSame('Product', $schemaInspector->getObjectSchema('Product')['name']);
        $this->assertSame(0, $mockHandler->count());
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::prefetchFullSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::getObjectSchema
     */
    public function testFallsBackToPerTypeFetchWhenTypeIsMissingFromPrefetchedSchema()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT'),
        ]));
        $mockHandler->append($this->createTypeResponse($this->createType('Product', 'OBJECT')));

        $this->assertSame('Product', $schemaInspector->getObjectSchema('Product')['name']);
        $this->assertSame(0, $mockHandler->count());
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::prefetchFullSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::schemaHasTruncatedTypeRefs
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::isTruncatedTypeRef
     */
    public function testEscalatesIntrospectionDepthWhenTypeRefsAreTruncated()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler);

        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT', [
                'fields' => [$this->createField('deep', $this->createTruncatedTypeRef())],
            ]),
        ]));
        $mockHandler->append($this->createFullSchemaResponse([
            $this->createType('Root', 'OBJECT', [
                'fields' => [$this->createField('deep', $this->createCompleteTypeRef())],
            ]),
        ]));

        $field = $schemaInspector->getQueryTypeSchema()['fields'][0];

        $this->assertSame('deep', $field['name']);
        $this->assertSame('String', $this->findInnermostTypeName($field['type']));
        $this->assertSame(0, $mockHandler->count(), 'A second, deeper prefetch must have been issued');
    }

    /**
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::fetchTypeSchema
     * @covers \GraphQL\SchemaGenerator\SchemaInspector::isTruncatedTypeRef
     */
    public function testEscalatesDepthForPerTypeFetchesWhenPrefetchIsDisabled()
    {
        $mockHandler = new MockHandler();
        $schemaInspector = $this->createInspector($mockHandler, false);

        $mockHandler->append($this->createTypeResponse($this->createType('Deep', 'OBJECT', [
            'fields' => [$this->createField('deep', $this->createTruncatedTypeRef())],
        ])));
        $mockHandler->append($this->createTypeResponse($this->createType('Deep', 'OBJECT', [
            'fields' => [$this->createField('deep', $this->createCompleteTypeRef())],
        ])));

        $field = $schemaInspector->getObjectSchema('Deep')['fields'][0];

        $this->assertSame('String', $this->findInnermostTypeName($field['type']));
        $this->assertSame(0, $mockHandler->count(), 'A second, deeper per-type fetch must have been issued');
    }

    /**
     * @param MockHandler $mockHandler
     * @param bool        $prefetch
     *
     * @return SchemaInspector
     */
    private function createInspector(MockHandler $mockHandler, bool $prefetch = true): SchemaInspector
    {
        $handler = HandlerStack::create($mockHandler);

        return new SchemaInspector(new Client('', [], ['handler' => $handler]), $prefetch);
    }

    /**
     * @param array       $types
     * @param string      $queryTypeName
     * @param string|null $mutationTypeName
     *
     * @return Response
     */
    private function createFullSchemaResponse(array $types, string $queryTypeName = 'Root', ?string $mutationTypeName = null): Response
    {
        return new Response(200, [], json_encode([
            'data' => [
                '__schema' => [
                    'queryType' => ['name' => $queryTypeName],
                    'mutationType' => $mutationTypeName === null ? null : ['name' => $mutationTypeName],
                    'types' => $types,
                ],
            ],
        ]));
    }

    /**
     * @param array $type
     *
     * @return Response
     */
    private function createTypeResponse(array $type): Response
    {
        return new Response(200, [], json_encode(['data' => ['__type' => $type]]));
    }

    /**
     * @param string $name
     * @param string $kind
     * @param array  $overrides
     *
     * @return array
     */
    private function createType(string $name, string $kind, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'kind' => $kind,
            'fields' => null,
            'inputFields' => null,
            'enumValues' => null,
            'possibleTypes' => null,
        ], $overrides);
    }

    /**
     * @param string $name
     * @param array  $typeRef
     *
     * @return array
     */
    private function createField(string $name, array $typeRef): array
    {
        return [
            'name' => $name,
            'isDeprecated' => false,
            'deprecationReason' => null,
            'type' => $typeRef,
            'args' => [],
        ];
    }

    /**
     * @return array
     */
    private function createTruncatedTypeRef(): array
    {
        $type = ['name' => null, 'kind' => 'NON_NULL'];
        for ($i = 0; $i < 9; $i++) {
            $type = ['name' => null, 'kind' => 'LIST', 'ofType' => $type];
        }

        return $type;
    }

    /**
     * @return array
     */
    private function createCompleteTypeRef(): array
    {
        $type = ['name' => 'String', 'kind' => 'SCALAR', 'ofType' => null];
        for ($i = 0; $i < 9; $i++) {
            $type = ['name' => null, 'kind' => 'LIST', 'ofType' => $type];
        }

        return $type;
    }

    /**
     * @param array $typeRef
     *
     * @return string|null
     */
    private function findInnermostTypeName(array $typeRef): ?string
    {
        while (($typeRef['ofType'] ?? null) !== null) {
            $typeRef = $typeRef['ofType'];
        }

        return $typeRef['name'];
    }
}
