<?php

namespace GraphQL\SchemaObject;

/**
 * Base for query objects of GraphQL interfaces. The fields shared by all implementations are selected directly, fields
 * of a specific implementation are selected through its inline fragment selector.
 *
 * Class InterfaceObject
 *
 * @package GraphQL\SchemaObject
 */
abstract class InterfaceObject extends AbstractTypeObject
{
}
