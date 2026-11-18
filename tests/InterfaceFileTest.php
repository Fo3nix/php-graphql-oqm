<?php

namespace GraphQL\Tests;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\InterfaceFile;

class InterfaceFileTest extends CodeFileTestCase
{
    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\InterfaceFile::generateClassName
     */
    public function testInterfaceWithoutParents()
    {
        $file = new InterfaceFile(static::getGeneratedFilesDir(), 'Plain');
        $file->setNamespace('Some\\Namespace');
        $file->addMethod('public function getId();');
        $file->writeFile();

        $this->assertSame(
            '<?php

namespace Some\\Namespace;

interface Plain
{
    public function getId();
}
',
            file_get_contents(static::getGeneratedFilesDir() . '/Plain.php')
        );
    }

    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\InterfaceFile::extendsInterface
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\CodeFile\InterfaceFile::generateClassName
     */
    public function testInterfaceExtendingInterfaces()
    {
        $file = new InterfaceFile(static::getGeneratedFilesDir(), 'Entity');
        $file->extendsInterface('Node');
        $file->extendsInterface('Named');
        $file->extendsInterface('Node');
        $file->writeFile();

        $this->assertSame(
            '<?php

interface Entity extends Node, Named
{}
',
            file_get_contents(static::getGeneratedFilesDir() . '/Entity.php')
        );
    }
}
