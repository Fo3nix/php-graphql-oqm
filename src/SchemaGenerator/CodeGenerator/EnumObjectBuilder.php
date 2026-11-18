<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;

/**
 * Class EnumObjectBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class EnumObjectBuilder implements ObjectBuilderInterface
{
    /**
     * @var ClassFile
     */
    protected $classFile;

    /**
     * EnumObjectBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $className = $classPrefix . $objectName . 'EnumObject';

        $this->classFile = new ClassFile($writeDir, $className);
        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\EnumObject');
        }
        $this->classFile->extendsClass('EnumObject');
    }

    /**
     * @param string $valueName
     * @param string|null $description
     */
    public function addEnumValue(string $valueName, ?string $description = null)
    {
        $constantName = strtoupper($valueName);
        $this->classFile->addConstant($constantName, $valueName, $description);
    }

    /**
     * Sets the description of the generated class, documented in its doc comment
     *
     * @param string|null $description
     */
    public function setDescription(?string $description)
    {
        $this->classFile->setDocComment($description);
    }

    /**
     * @return void
     */
    public function build(): void
    {
        $this->classFile->writeFile();
    }
}