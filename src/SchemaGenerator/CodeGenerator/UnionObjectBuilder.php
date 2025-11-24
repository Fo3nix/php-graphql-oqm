<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class EnumObjectBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class UnionObjectBuilder implements ObjectBuilderInterface
{
    /**
     * @var ClassFile
     */
    protected $classFile;

    private $classPrefix = '';

    /**
     * EnumObjectBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $className = $classPrefix . $objectName . 'UnionObject';
        $this->classPrefix = $classPrefix;

        $this->classFile = new ClassFile($writeDir, $className);
        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\UnionObject');
        }
        $this->classFile->extendsClass('UnionObject');
    }

    /**
     * @param string $typeName
     */
    public function addPossibleType(string $typeName)
    {
        $typeName = $this->classPrefix . $typeName;
        $upperCamelCaseTypeName = StringLiteralFormatter::formatUpperCamelCase($typeName);
        $objectClassName = $typeName . 'QueryObject';
        $method = "public function on$upperCamelCaseTypeName()
{
    \$object = new $objectClassName();
    \$this->addPossibleType(\$object);

    return \$object;
}";
        $this->classFile->addMethod($method);
    }

    /**
     * @return void
     */
    public function build(): void
    {
        $this->classFile->writeFile();
    }
}
