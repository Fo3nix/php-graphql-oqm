<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\SchemaObject\QueryObject;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class QueryObjectClassBuilder
 *
 * @package GraphQL\SchemaManager\CodeGenerator
 */
class QueryObjectClassBuilder extends ObjectClassBuilder
{

    private const BASE_CLASS_METHODS = [
        '__construct',
        'setAlias',
        'getQuery',
        'selectField',
        'setArgument',
        'setVariable',
    ];

    private $classPrefix = '';


    /**
     * QueryObjectClassBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $className = $classPrefix . $objectName . 'QueryObject';

        $this->classPrefix = $classPrefix;

        $this->classFile = new ClassFile($writeDir, $className);
        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\QueryObject');
        }
        $this->classFile->extendsClass('QueryObject');

        // Special case for handling root query object
        if ($objectName === QueryObject::ROOT_QUERY_OBJECT_NAME) {
            $objectName = '';
        }
        $this->classFile->addConstant('OBJECT_NAME', $objectName);
    }

    /**
     * @param string $fieldName
     */
    public function addScalarField(string $fieldName, bool $isDeprecated, ?string $deprecationReason)
    {
        $upperCamelCaseProp = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $this->addSimpleSelector($fieldName, $upperCamelCaseProp, $isDeprecated, $deprecationReason);
    }

    /**
     * @param string $fieldName
     * @param string $typeName
     * @param string $typeKind
     * @param string $argsObjectName
     * @param bool $isDeprecated
     * @param string|null $deprecationReason
     */
    public function addObjectField(string $fieldName, string $typeName, string $typeKind, string $argsObjectName, bool $isDeprecated, ?string $deprecationReason)
    {
        $upperCamelCaseProp = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $this->addObjectSelector($fieldName, $upperCamelCaseProp, $typeName, $typeKind, $argsObjectName, $isDeprecated, $deprecationReason);
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     * @param bool $isDeprecated
     * @param string|null $deprecationReason
     */
    protected function addSimpleSelector(string $propertyName, string $upperCamelName, bool $isDeprecated, ?string $deprecationReason)
    {
        $methodName = $this->buildSelectorMethodName($upperCamelName);
        $method = "public function $methodName()
{
    \$this->selectField(\"$propertyName\");

    return \$this;
}";
        $this->classFile->addMethod($method, $isDeprecated, $deprecationReason);
    }

    /**
     * @param string $fieldName
     * @param string $upperCamelName
     * @param string $fieldTypeName
     * @param string $fieldTypeKind
     * @param string $argsObjectName
     * @param bool $isDeprecated
     * @param string|null $deprecationReason
     */
    protected function addObjectSelector(string $fieldName, string $upperCamelName, string $fieldTypeName, string $fieldTypeKind, string $argsObjectName, bool $isDeprecated, ?string $deprecationReason)
    {
        $objectClass = $this->classPrefix . $fieldTypeName . ($fieldTypeKind === FieldTypeKindEnum::UNION_OBJECT ? 'UnionObject' : 'QueryObject');
        $methodName = $this->buildSelectorMethodName($upperCamelName);
        $method = "public function $methodName({$this->classPrefix}$argsObjectName \$argsObject = null)
{
    \$object = new $objectClass(\"$fieldName\");
    if (\$argsObject !== null) {
        \$object->appendArguments(\$argsObject->toArray());
    }
    \$this->selectField(\$object);

    return \$object;
}";
        $this->classFile->addMethod($method, $isDeprecated, $deprecationReason);
    }

    private function buildSelectorMethodName(string $upperCamelName): string
    {
        $methodName = "select$upperCamelName";
        foreach (self::BASE_CLASS_METHODS as $baseClassMethod) {
            if (strcasecmp($methodName, $baseClassMethod) === 0) {
                return $methodName . '_';
            }
        }

        return $methodName;
    }

    /**
     * This method builds the class and writes it to the file system
     */
    public function build(): void
    {
        $this->classFile->writeFile();
    }
}
