<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\Util\StringLiteralFormatter;

/**
 * Builds the query class of a GraphQL interface: selectors for the interface's own fields, plus an inline fragment
 * selector for every type implementing it
 *
 * Class InterfaceObjectClassBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class InterfaceObjectClassBuilder extends QueryObjectClassBuilder
{
    protected const CLASS_SUFFIX = 'InterfaceObject';

    protected const BASE_CLASS = 'InterfaceObject';

    /**
     * @param string $typeName
     */
    public function addPossibleType(string $typeName)
    {
        $upperCamelCaseTypeName = StringLiteralFormatter::formatUpperCamelCase($typeName);
        $objectClassName = $this->classPrefix . $typeName . 'QueryObject';
        $method = "public function on$upperCamelCaseTypeName()
{
    \$object = new $objectClassName();
    \$this->addPossibleType(\$object);

    return \$object;
}";
        $this->classFile->addMethod($method);
    }
}
