<?php

namespace GraphQL\SchemaGenerator;

use GraphQL\Client;

/**
 * Class SchemaInspector
 *
 * @codeCoverageIgnore
 *
 * @package GraphQL\SchemaGenerator
 */
class SchemaInspector
{
    private const TYPE_SUB_QUERY_DEPTH = 8;

    /**
     * @var Client
     */
    protected $client;

    private static function buildTypeSubQuery(int $depth = self::TYPE_SUB_QUERY_DEPTH): string
    {
        $subQuery = "name\nkind";
        for ($i = 1; $i < $depth; $i++) {
            $subQuery = "name\nkind\nofType{\n$subQuery\n}";
        }

        return "type{\nname\nkind\ndescription\nofType{\n$subQuery\n}\n}";
    }

    /**
     * SchemaInspector constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return array
     */
    public function getQueryTypeSchema(): array
    {
        $schemaQuery = "{
  __schema{
    queryType{
      name
      kind
      description
      fields(includeDeprecated: true){
        name
        description
        isDeprecated
        deprecationReason
        " . static::buildTypeSubQuery() . "
        args{
          name
          description
          defaultValue
          " . static::buildTypeSubQuery() . "
        }
      }
    }
  }
}";
        $response = $this->client->runRawQuery($schemaQuery, true);

        return $response->getData()['__schema']['queryType'];
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getObjectSchema(string $objectName): array
    {
        $schemaQuery = "{
  __type(name: \"$objectName\") {
    name
    kind
    fields(includeDeprecated: true){
      name
      description
      isDeprecated
      deprecationReason
      " . static::buildTypeSubQuery() . "
      args{
        name
        description
        defaultValue
        " . static::buildTypeSubQuery() . "
      }
    }
  }
}";
        $response = $this->client->runRawQuery($schemaQuery, true);

        return $response->getData()['__type'];
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getInputObjectSchema(string $objectName): array
    {
        $schemaQuery = "{
  __type(name: \"$objectName\") {
    name
    kind
    inputFields {
      name
      description
      defaultValue
      " . static::buildTypeSubQuery() . "
    }
  }
}";
        $response = $this->client->runRawQuery($schemaQuery, true);

        return $response->getData()['__type'];
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getEnumObjectSchema(string $objectName): array
    {
        $schemaQuery = "{
  __type(name: \"$objectName\") {
    name
    kind
    enumValues {
      name
      description
    }
  }
}";
        $response = $this->client->runRawQuery($schemaQuery, true);

        return $response->getData()['__type'];
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getUnionObjectSchema(string $objectName): array
    {
        $schemaQuery = "{
  __type(name: \"$objectName\") {
    name
    kind
    possibleTypes {
      kind
      name
    }
  }
}";
        $response = $this->client->runRawQuery($schemaQuery, true);

        return $response->getData()['__type'];
    }
}
