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
    public function selectTitle()
    {
        $this->selectField("title");

        return $this;
    }

    /**
     * Old title.
     *
     * @deprecated Use title
     */
    public function selectOldTitle()
    {
        $this->selectField("old_title");

        return $this;
    }
}
