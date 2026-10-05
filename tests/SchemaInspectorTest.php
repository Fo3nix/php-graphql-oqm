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
     * @param array  $types
     * @param string $queryTypeName
     *
     * @return Response
     */
    private function createFullSchemaResponse(array $types, string $queryTypeName = 'Root'): Response
    {
        return new Response(200, [], json_encode([
            'data' => [
                '__schema' => [
                    'queryType' => ['name' => $queryTypeName],
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
