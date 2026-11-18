<?php

namespace GraphQL\SchemaObject;

use GraphQL\RawObject;

/**
 * A reference to a variable of the operation, written as "$name" in arguments and input objects
 *
 * Class VariableReference
 *
 * @package GraphQL\SchemaObject
 */
class VariableReference extends RawObject
{
    /**
     * @param object      $owner        The arguments or input object the reference is set on
     * @param string      $propertyName The argument or input field the reference is set on
     * @param string|null $variableName Defaults to the property name
     *
     * @return VariableReference
     */
    public static function create(object $owner, string $propertyName, ?string $variableName = null): self
    {
        if (!property_exists($owner, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('%s has no argument or field "%s"', get_class($owner), $propertyName));
        }
        $variableName = $variableName ?? $propertyName;
        if (!preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $variableName)) {
            throw new \InvalidArgumentException("\"$variableName\" is not a valid variable name");
        }

        return new self('$' . $variableName);
    }
}
