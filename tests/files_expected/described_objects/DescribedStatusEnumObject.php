<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\EnumObject;

/**
 * Product status.
 */
class DescribedStatusEnumObject extends EnumObject
{
    /**
     * Visible in the shop.
     */
    const ACTIVE = "ACTIVE";
    const DRAFT = "DRAFT";
}
