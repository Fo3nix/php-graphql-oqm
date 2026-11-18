<?php

namespace GraphQL\SchemaObject;

use GraphQL\InlineFragment;

/**
 * Base for query objects of GraphQL abstract types (unions and interfaces). The concrete type of the returned object is
 * only known at runtime, so __typename is always selected and type specific fields are selected with inline fragments.
 *
 * Class AbstractTypeObject
 *
 * @package GraphQL\SchemaObject
 */
abstract class AbstractTypeObject extends QueryObject
{
    /**
     * AbstractTypeObject constructor.
     *
     * @param string $fieldName
     */
    public function __construct(string $fieldName = '')
    {
        parent::__construct($fieldName);
        $this->selectTypename();
    }

    /**
     * @param QueryObject $possibleType
     */
    protected function addPossibleType(QueryObject $possibleType)
    {
        $fragment = new InlineFragment($possibleType::OBJECT_NAME, $possibleType);
        $this->selectField($fragment);
    }
}
