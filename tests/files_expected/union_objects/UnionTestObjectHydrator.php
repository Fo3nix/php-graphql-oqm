<?php

namespace GraphQL\Tests\SchemaObject;

class UnionTestObjectHydrator
{
    /**
     * Hydrates the result class matching the __typename of the data
     *
     * @param array $data
     * @return UnionObject1|UnionObject2
     */
    public static function fromArray(array $data)
    {
        if (!isset($data['__typename'])) {
            throw new \InvalidArgumentException('Cannot hydrate UnionTestObject: the data has no __typename, select it in the query');
        }
        switch ($data['__typename']) {
            case 'UnionObject1':
                return UnionObject1::fromArray($data);
            case 'UnionObject2':
                return UnionObject2::fromArray($data);
            default:
                throw new \InvalidArgumentException('Cannot hydrate UnionTestObject: unknown type "' . $data['__typename'] . '", regenerate the schema objects');
        }
    }

    /**
     * Converts a hydrated object to an array, including its __typename so it can be hydrated again
     *
     * @param UnionObject1|UnionObject2 $object
     * @return array
     */
    public static function toArray($object): array
    {
        if ($object instanceof UnionObject1) {
            return ['__typename' => 'UnionObject1'] + $object->asArray();
        }
        if ($object instanceof UnionObject2) {
            return ['__typename' => 'UnionObject2'] + $object->asArray();
        }
        throw new \InvalidArgumentException('Cannot convert ' . get_class($object) . ' to an array of UnionTestObject');
    }
}
