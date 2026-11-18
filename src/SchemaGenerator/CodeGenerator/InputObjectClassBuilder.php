<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class InputObjectClassBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class InputObjectClassBuilder extends ObjectClassBuilder
{

    private $classPrefix = '';

    /**
     * SchemaObjectBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $className = $classPrefix . $objectName . 'InputObject';
        $this->classPrefix = $classPrefix;

        $this->classFile = new ClassFile($writeDir, $className);
        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\InputObject');
        }
        $this->classFile->extendsClass('InputObject');
    }

    /**
     * @param string $argumentName
     * @param string|null $description
     */
    public function addScalarValue(string $argumentName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addScalarSetter($argumentName, $upperCamelCaseArg, $description);
    }

    /**
     * @param string $argumentName
     * @param string $typeName
     * @param string|null $description
     */
    public function addListValue(string $argumentName, string $typeName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addListSetter($argumentName, $upperCamelCaseArg, $typeName, $description);
    }

    /**
     * @param string $argumentName
     * @param string $typeName
     * @param string|null $description
     */
    public function addInputObjectValue(string $argumentName, string $typeName, ?string $description = null)
    {
        $typeName .= 'InputObject';
        $typeName = $this->classPrefix . $typeName;

        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addObjectSetter($argumentName, $upperCamelCaseArg, $typeName, $description);
    }

    /**
     * @return void
     */
    /**
     * @param string $argumentName
     * @param string $typeName
     * @param string|null $description
     */
    public function addEnumValue(string $argumentName, string $typeName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addEnumSetter($argumentName, $upperCamelCaseArg, $this->classPrefix . $typeName, $description);
    }

    /**
     * @param string $argumentName
     * @param string|null $description
     */
    public function addEnumListValue(string $argumentName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addEnumListSetter($argumentName, $upperCamelCaseArg, $description);
    }

    public function build(): void
    {
        $this->classFile->writeFile();
    }
}