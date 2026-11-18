<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\InterfaceFile;

/**
 * Builds the PHP interface for a GraphQL interface, declaring the getters of the fields shared by all result classes
 * implementing it
 *
 * Class ResultInterfaceBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class ResultInterfaceBuilder extends ResultObjectClassBuilder
{
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $this->classFile = new InterfaceFile($writeDir, $classPrefix . $objectName);
        $this->classFile->setNamespace($namespace);
        $this->generationNamespace = $namespace;
        $this->classPrefix = $classPrefix;
    }

    /**
     * Declares the getter of a field shared by all implementations
     *
     * @param string   $fieldName
     * @param string   $typeName
     * @param string   $typeKind
     * @param array    $typeWrappers
     * @param string[] $possibleTypes Names of the types a union field can hold
     */
    public function addField(string $fieldName, string $typeName, string $typeKind, array $typeWrappers, array $possibleTypes = [])
    {
        $info = $this->resolveField($fieldName, $typeName, $typeKind, $typeWrappers, $possibleTypes);
        $this->classFile->addMethod($this->generateGetter($fieldName, $info['docType'], false));
    }

    public function build(): void
    {
        $this->classFile->addMethod("/**
 * Converts this object to an array.
 * @return array
 */
public function asArray(): array;");
        $this->classFile->writeFile();
    }
}
