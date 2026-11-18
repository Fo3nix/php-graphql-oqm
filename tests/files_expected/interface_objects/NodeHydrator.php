<?php

namespace GraphQL\Tests\SchemaObject;

class NodeHydrator
{
    /**
     * Hydrates the result class matching the __typename of the data
     *
     * @param array $data
     * @return Node
     */
    public static function fromArray(array $data): Node
    {
        if (!isset($data['__typename'])) {
            throw new \InvalidArgumentException('Cannot hydrate Node: the data has no __typename, select it in the query');
        }
        switch ($data['__typename']) {
            case 'Product':
                return Product::fromArray($data);
            case 'Collection':
                return Collection::fromArray($data);
            default:
                throw new \InvalidArgumentException('Cannot hydrate Node: unknown type "' . $data['__typename'] . '", regenerate the schema objects');
        }
    }

    /**
     * Converts a hydrated object to an array, including its __typename so it can be hydrated again
     *
     * @param Node $object
     * @return array
     */
    public static function toArray(Node $object): array
    {
        if ($object instanceof Product) {
            return ['__typename' => 'Product'] + $object->asArray();
        }
        if ($object instanceof Collection) {
            return ['__typename' => 'Collection'] + $object->asArray();
        }
        throw new \InvalidArgumentException('Cannot convert ' . get_class($object) . ' to an array of Node');
    }
}
