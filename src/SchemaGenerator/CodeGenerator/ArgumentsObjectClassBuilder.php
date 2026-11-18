<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class ArgumentsObjectClassBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class ArgumentsObjectClassBuilder extends ObjectClassBuilder
{

    private $classPrefix = '';

    /**
     * ArgumentsObjectClassBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $this->classFile = new ClassFile($writeDir, $classPrefix . $objectName);
        $this->classPrefix = $classPrefix;

        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\ArgumentsObject');
        }
        $this->classFile->extendsClass('ArgumentsObject');
    }

    /**
     * @param string $argumentName
     * @param string|null $description
     */
    public function addScalarArgument(string $argumentName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addScalarSetter($argumentName, $upperCamelCaseArg, $description);
    }

    /**
     * @param string string $argumentName
     * @param string string $typeName
     * @param string|null $description
     */
    public function addListArgument(string $argumentName, string $typeName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addListSetter($argumentName, $upperCamelCaseArg, $typeName, $description);
    }

    /**
     * @param string $argumentName
     * @param string|null $description
     */
    public function addEnumListArgument(string $argumentName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addEnumListSetter($argumentName, $upperCamelCaseArg, $description);
    }

    /**
     * @param string $argumentName
     * @param string $typeName
     * @param string|null $description
     */
    public function addInputEnumArgument(string $argumentName, string $typeName, ?string $description = null)
    {
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addEnumSetter($argumentName, $upperCamelCaseArg, $this->classPrefix . $typeName, $description);
    }

    /**
     * @param string $argumentName
     * @param string $typeName
     * @param string|null $description
     */
    public function addInputObjectArgument(string $argumentName, string $typeName, ?string $description = null)
    {
        $typeName .= 'InputObject';
        $upperCamelCaseArg = StringLiteralFormatter::formatUpperCamelCase($argumentName);
        $this->addProperty($argumentName);
        $this->addObjectSetter($argumentName, $upperCamelCaseArg, $this->classPrefix . $typeName, $description);
    }

    /**
     * @return void
     */
    public function build(): void
    {
        $this->classFile->writeFile();
    }
}