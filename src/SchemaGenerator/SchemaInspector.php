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
    private const MAX_TYPE_SUB_QUERY_DEPTH = 64;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var bool
     */
    private $prefetch;

    /**
     * @var array|null
     */
    private $prefetchedTypes;

    /**
     * @var array|null
     */
    private $prefetchedQueryType;

    /**
     * @var bool
     */
    private $prefetchFailed = false;

    /**
     * SchemaInspector constructor.
     *
     * @param Client $client
     * @param bool   $prefetch
     */
    public function __construct(Client $client, bool $prefetch = true)
    {
        $this->client = $client;
        $this->prefetch = $prefetch;
    }

    /**
     * @return array
     */
    public function getQueryTypeSchema(): array
    {
        $queryType = $this->getPrefetchedQueryType();
        if ($queryType !== null) {
            return $queryType;
        }

        $schemaQuery = "{
  __schema{
    queryType{
      name
      kind
      fields(includeDeprecated: true){
        name
        isDeprecated
        deprecationReason
        " . self::buildTypeSubQuery() . "
        args{
          name
          " . self::buildTypeSubQuery() . "
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
        $type = $this->getPrefetchedType($objectName);
        if ($type !== null) {
            return $type;
        }

        return $this->fetchTypeSchema(function (int $depth) use ($objectName): string {
            return "{
  __type(name: \"$objectName\") {
    name
    kind
    fields(includeDeprecated: true){
      name
      isDeprecated
      deprecationReason
      " . self::buildTypeSubQuery($depth) . "
      args{
        name
        " . self::buildTypeSubQuery($depth) . "
      }
    }
  }
}";
        });
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getInputObjectSchema(string $objectName): array
    {
        $type = $this->getPrefetchedType($objectName);
        if ($type !== null) {
            return $type;
        }

        return $this->fetchTypeSchema(function (int $depth) use ($objectName): string {
            return "{
  __type(name: \"$objectName\") {
    name
    kind
    inputFields {
      name
      " . self::buildTypeSubQuery($depth) . "
    }
  }
}";
        });
    }

    /**
     * @param string $objectName
     *
     * @return array
     */
    public function getEnumObjectSchema(string $objectName): array
    {
        $type = $this->getPrefetchedType($objectName);
        if ($type !== null) {
            return $type;
        }

        $schemaQuery = "{
  __type(name: \"$objectName\") {
    name
    kind
    enumValues {
      name
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
        $type = $this->getPrefetchedType($objectName);
        if ($type !== null) {
            return $type;
        }

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

    /**
     * @param string $objectName
     *
     * @return array|null
     */
    private function getPrefetchedType(string $objectName): ?array
    {
        if (!$this->prefetch) {
            return null;
        }
        $this->prefetchFullSchema();

        return $this->prefetchedTypes[$objectName] ?? null;
    }

    /**
     * @return array|null
     */
    private function getPrefetchedQueryType(): ?array
    {
        if (!$this->prefetch) {
            return null;
        }
        $this->prefetchFullSchema();

        return $this->prefetchedQueryType;
    }

    private function prefetchFullSchema(): void
    {
        if ($this->prefetchedTypes !== null || $this->prefetchFailed) {
            return;
        }

        $depth = self::TYPE_SUB_QUERY_DEPTH;
        try {
            do {
                $response = $this->client->runRawQuery($this->buildFullSchemaQuery($depth), true);
                $data = $this->extractResponseData($response);
                $schema = is_array($data) ? ($data['__schema'] ?? null) : null;
                if (!is_array($schema) || !isset($schema['types'], $schema['queryType']['name'])) {
                    $this->prefetchFailed = true;
                    return;
                }
                $depth *= 2;
            } while ($depth <= self::MAX_TYPE_SUB_QUERY_DEPTH && $this->schemaHasTruncatedTypeRefs($schema));

            $types = [];
            foreach ($schema['types'] as $type) {
                if (isset($type['name'])) {
                    $types[$type['name']] = $type;
                }
            }
            $queryTypeName = $schema['queryType']['name'];
            $this->prefetchedTypes = $types;
            $this->prefetchedQueryType = $types[$queryTypeName] ?? null;
        } catch (\Throwable $throwable) {
            $this->prefetchFailed = true;
        }
    }

    /**
     * @param int $depth
     *
     * @return string
     */
    private function buildFullSchemaQuery(int $depth = self::TYPE_SUB_QUERY_DEPTH): string
    {
        return "{
  __schema{
    queryType{
      name
    }
    types{
      name
      kind
      fields(includeDeprecated: true){
        name
        isDeprecated
        deprecationReason
        " . self::buildTypeSubQuery($depth) . "
        args{
          name
          " . self::buildTypeSubQuery($depth) . "
        }
      }
      inputFields{
        name
        " . self::buildTypeSubQuery($depth) . "
      }
      enumValues{
        name
      }
      possibleTypes{
        kind
        name
      }
    }
  }
}";
    }

    /**
     * @param callable $queryBuilder
     *
     * @return array
     */
    private function fetchTypeSchema(callable $queryBuilder): array
    {
        $depth = self::TYPE_SUB_QUERY_DEPTH;
        $type = null;
        do {
            try {
                $response = $this->client->runRawQuery($queryBuilder($depth), true);
                $data = $this->extractResponseData($response);
                $type = is_array($data) ? ($data['__type'] ?? null) : null;
            } catch (\Throwable $throwable) {
                if ($type === null) {
                    throw $throwable;
                }
                return $type;
            }
            $depth *= 2;
        } while ($depth <= self::MAX_TYPE_SUB_QUERY_DEPTH && is_array($type) && $this->containsTruncatedTypeRef($type));

        if (!is_array($type)) {
            throw new \RuntimeException('No __type data found in schema response');
        }

        return $type;
    }

    /**
     * @param mixed $response
     *
     * @return mixed
     */
    private function extractResponseData($response)
    {
        $results = $response->getResults();
        if (is_array($results)) {
            return $results['data'] ?? null;
        }
        if (is_object($results)) {
            return $results->data ?? null;
        }

        return null;
    }

    /**
     * @param array $schema
     *
     * @return bool
     */
    private function schemaHasTruncatedTypeRefs(array $schema): bool
    {
        foreach ($schema['types'] as $type) {
            if ($this->containsTruncatedTypeRef($type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $type
     *
     * @return bool
     */
    private function containsTruncatedTypeRef(array $type): bool
    {
        foreach (['fields', 'inputFields'] as $fieldSetName) {
            foreach ($type[$fieldSetName] ?? [] as $field) {
                if (isset($field['type']) && $this->isTruncatedTypeRef($field['type'])) {
                    return true;
                }
                foreach ($field['args'] ?? [] as $argument) {
                    if (isset($argument['type']) && $this->isTruncatedTypeRef($argument['type'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array $type
     *
     * @return bool
     */
    private function isTruncatedTypeRef(array $type): bool
    {
        while (($type['ofType'] ?? null) !== null) {
            $type = $type['ofType'];
        }

        return $type['name'] === null;
    }

    /**
     * @param int $depth
     *
     * @return string
     */
    private static function buildTypeSubQuery(int $depth = self::TYPE_SUB_QUERY_DEPTH): string
    {
        $subQuery = "name\nkind";
        for ($i = 1; $i < $depth; $i++) {
            $subQuery = "name\nkind\nofType{\n$subQuery\n}";
        }

        return "type{\nname\nkind\nofType{\n$subQuery\n}\n}";
    }
}
