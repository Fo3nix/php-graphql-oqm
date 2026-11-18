<?php

namespace GraphQL\SchemaObject;

/**
 * Thrown when an argument or input field the schema requires is not set, before the request is sent
 *
 * Class MissingRequiredValueException
 *
 * @package GraphQL\SchemaObject
 */
class MissingRequiredValueException extends \InvalidArgumentException
{
    /**
     * @param ArgumentsObject|InputObject $object
     * @param array                       $values        The object's values, read in its own scope as they are protected
     * @param string[]                    $requiredNames
     * @param string                      $valueKind     "argument" or "field", used in the message
     */
    public static function assertSet(object $object, array $values, array $requiredNames, string $valueKind): void
    {
        $missing = array_filter($requiredNames, function (string $name) use ($values) {
            return ($values[$name] ?? null) === null;
        });
        if (!empty($missing)) {
            throw new self(sprintf(
                '%s: missing required %s%s "%s"',
                get_class($object),
                $valueKind,
                count($missing) > 1 ? 's' : '',
                implode('", "', $missing)
            ));
        }
    }
}
