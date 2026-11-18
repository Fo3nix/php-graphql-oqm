<?php

namespace GraphQL\SchemaObject;

use GraphQL\RawObject;

/**
 * Class ArgumentsObject
 *
 * @package GraphQL\SchemaObject
 */
abstract class ArgumentsObject
{
    /**
     * Names of the arguments the schema requires (non-null without a default value)
     *
     * @var string[]
     */
    protected const REQUIRED = [];

    /**
     * @return array
     */
    public function toArray(): array
    {
        MissingRequiredValueException::assertSet($this, get_object_vars($this), static::REQUIRED, 'argument');

        $argsArray = [];
        foreach ($this as $name => $value) {
            if ($value !== null) {
                $argsArray[$name] = $value;
            }
        }

        return $argsArray;
    }

    /**
     * Sets an argument to a variable of the operation, declared with setVariable() on the root object
     *
     * @param string      $argumentName The name of the argument as in the schema
     * @param string|null $variableName The variable name without the leading "$", defaults to the argument name
     *
     * @return $this
     */
    public function useVariable(string $argumentName, ?string $variableName = null): static
    {
        $this->$argumentName = VariableReference::create($this, $argumentName, $variableName);

        return $this;
    }
}
