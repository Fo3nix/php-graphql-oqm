<?php

namespace GraphQL\SchemaObject;

use GraphQL\RawObject;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class InputObject
 *
 * @package GraphQL\SchemaObject
 */
abstract class InputObject
{
    /**
     * Names of the fields the schema requires (non-null without a default value)
     *
     * @var string[]
     */
    protected const REQUIRED = [];

    /**
     * @return string
     */
    public function __toString()
    {
        MissingRequiredValueException::assertSet($this, get_object_vars($this), static::REQUIRED, 'field');

        $objectString = '{';
        $first = true;

        // TODO: Merge this code block with Query::constructArguments
        foreach ($this as $name => $value) {
            // Only unset values are omitted, false, 0, "" and [] are valid input values
            if ($value === null) continue;

            // Append space at the beginning if it's not the first item on the list
            if ($first) {
                $first = false;
            } else {
                $objectString .= ', ';
            }

            // Convert argument values to graphql string literal equivalent
            if (is_scalar($value)) {
                // Convert scalar value to its literal in graphql
                $value = StringLiteralFormatter::formatValueForRHS($value);
            } elseif (is_array($value)) {
                // Convert PHP array to its array representation in graphql arguments
                $value = StringLiteralFormatter::formatArrayForGQLQuery($value);
            }
            // TODO: Handle cases where a non-string-convertible object is added to the arguments
            $objectString .= $name . ': ' . $value;
        }
        $objectString .= '}';

        return $objectString;
    }

    /**
     * @return RawObject
     */
    public function toRawObject(): RawObject
    {
        return new RawObject((string) $this);
    }

    /**
     * Sets a field to a variable of the operation, declared with setVariable() on the root object
     *
     * @param string      $fieldName    The name of the input field as in the schema
     * @param string|null $variableName The variable name without the leading "$", defaults to the field name
     *
     * @return $this
     */
    public function useVariable(string $fieldName, ?string $variableName = null): static
    {
        $this->$fieldName = VariableReference::create($this, $fieldName, $variableName);

        return $this;
    }

    /**
     * Converts the input to the plain array to send as the value of a variable. Enum values become strings and
     * nested input objects arrays, unset fields are omitted.
     *
     * @return array
     */
    public function toArray(): array
    {
        MissingRequiredValueException::assertSet($this, get_object_vars($this), static::REQUIRED, 'field');

        $array = [];
        foreach ($this as $name => $value) {
            if ($value !== null) {
                $array[$name] = self::toVariableValue($value);
            }
        }

        return $array;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function toVariableValue($value)
    {
        if ($value instanceof InputObject) {
            return $value->toArray();
        }
        if ($value instanceof VariableReference) {
            throw new \LogicException("A variable value cannot reference the variable $value");
        }
        if ($value instanceof RawObject) {
            // Enum values are stored as raw objects to write them unquoted in queries
            return (string) $value;
        }
        if (is_array($value)) {
            return array_map(function ($item) {
                return self::toVariableValue($item);
            }, $value);
        }

        return $value;
    }
}