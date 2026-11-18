<?php

namespace GraphQL\Tests;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\DocComment;
use PHPUnit\Framework\TestCase;

class DocCommentTest extends TestCase
{
    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\DocComment::format
     */
    public function testNothingToDocument()
    {
        $this->assertSame('', DocComment::format(null));
        $this->assertSame('', DocComment::format("  \n "));
    }

    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\DocComment::format
     */
    public function testDescriptionAndTags()
    {
        $this->assertSame(
            "/**\n * First line.\n *\n * Second paragraph.\n *\n * @deprecated Use other\n */\n",
            DocComment::format("First line.\n\nSecond paragraph.\n", ['@deprecated Use other'])
        );
        $this->assertSame("/**\n * @return string\n */\n", DocComment::format(null, ['@return string']));
    }

    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\DocComment::format
     */
    public function testCommentTerminatorIsEscaped()
    {
        $this->assertSame("/**\n * Matches /*.css *\\/ files\n */\n", DocComment::format('Matches /*.css */ files'));
    }
}
