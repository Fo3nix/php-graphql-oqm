<?php

namespace GraphQL\Tests\SchemaObject;

use GraphQL\SchemaObject\ArgumentsObject;

class RootMutationCreateThingArgumentsObject extends ArgumentsObject
{
    const REQUIRED = ["input"];

    protected $input;

    public function setInput(ThingInputInputObject $thingInputInputObject): static
    {
        $this->input = $thingInputInputObject;

        return $this;
    }
}
