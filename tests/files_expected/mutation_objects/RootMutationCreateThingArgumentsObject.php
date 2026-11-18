<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class RootMutationCreateThingArgumentsObject extends ArgumentsObject
{
    const REQUIRED = ["input"];

    protected $input;

    public function setInput(ThingInputInputObject $thingInputInputObject)
    {
        $this->input = $thingInputInputObject;

        return $this;
    }
}
