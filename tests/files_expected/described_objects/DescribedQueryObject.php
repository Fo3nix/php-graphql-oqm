<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\QueryObject;

/**
 * A described object.
 */
class DescribedQueryObject extends QueryObject
{
    const OBJECT_NAME = "Described";

    /**
     * The title.
     */
    public function selectTitle(): static
    {
        $this->selectField("title");

        return $this;
    }

    /**
     * Old title.
     *
     * @deprecated Use title
     */
    public function selectOldTitle(): static
    {
        $this->selectField("old_title");

        return $this;
    }
}
