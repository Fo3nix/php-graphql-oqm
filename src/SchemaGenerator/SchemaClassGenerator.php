<?php

namespace GraphQL\SchemaGenerator;

use GraphQL\Client;
use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\ArgumentsObjectClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\EnumObjectBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\InputObjectClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\InterfaceObjectClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\ObjectBuilderInterface;
use GraphQL\SchemaGenerator\CodeGenerator\QueryObjectClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\ResultHydratorClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\ResultInterfaceBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\ResultObjectClassBuilder;
use GraphQL\SchemaGenerator\CodeGenerator\UnionObjectBuilder;
use GraphQL\SchemaObject\MutationObject;
use GraphQL\SchemaObject\QueryObject;
use GraphQL\Util\StringLiteralFormatter;
use RuntimeException;

/**
 * This class scans the GraphQL API schema and generates Classes that map to the schema objects' structure
 *
 * Class SchemaClassGenerator
 *
 * @package GraphQL
 */
class SchemaClassGenerator
{
    /**
     * @var SchemaInspector
     */
    protected $schemaInspector;

    /**
     * @var string
     */
	private $writeDir;

    /**
     * @var string
     */
	private $generationNamespace;

    /**
     * @var string Prefix to prepend to generated class names (e.g. "PREFIX" -> PREFIXObjectA)
     */
    private $classPrefix;

    /**
     * This array is used as a set to store the already generated objects
     * Array structure: [$objectName] => true
     *AND complete covering the schema scanner class
     * @var array
     */
	private $generatedObjects;

    /**
     * Names of the possible types of every generated union and interface
     * Array structure: [$typeName] => string[]
     *
     * @var array
     */
    private $possibleTypes = [];

    /**
     * SchemaClassGenerator constructor.
     *
     * @param Client $client
     * @param string $writeDir
     * @param string $namespace
     * @param string $classPrefix
     * @param bool   $prefetch
     */
	public function __construct(Client $client, string $writeDir = '', string $namespace = ObjectBuilderInterface::DEFAULT_NAMESPACE, string $classPrefix = '', bool $prefetch = true)
    {
        $this->schemaInspector     = new SchemaInspector($client, $prefetch);
        $this->generatedObjects    = [];
        $this->writeDir            = $writeDir;
        $this->generationNamespace = $namespace;
        $this->classPrefix         = $classPrefix;
        $this->setWriteDir();
    }

    /**
     * @return bool
     */
	public function generateRootQueryObject(): bool
	{
        return $this->generateRootObject($this->schemaInspector->getQueryTypeSchema(), QueryObject::ROOT_QUERY_OBJECT_NAME);
    }

    /**
     * Generates the root mutation object, if the API exposes a mutation type
     *
     * @return bool False when the API does not support mutations
     */
    public function generateRootMutationObject(): bool
    {
        $objectArray = $this->schemaInspector->getMutationTypeSchema();
        if ($objectArray === null) {
            return false;
        }

        return $this->generateRootObject($objectArray, MutationObject::ROOT_MUTATION_OBJECT_NAME);
    }

    /**
     * @param array  $objectArray
     * @param string $rootObjectName
     *
     * @return bool
     */
    private function generateRootObject(array $objectArray, string $rootObjectName): bool
    {
        $typeName = $objectArray['name'];

        if (array_key_exists($typeName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$typeName] = true;

        $objectBuilder = new QueryObjectClassBuilder($this->writeDir, $rootObjectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);
        $this->appendQueryObjectFields($objectBuilder, $rootObjectName, $objectArray['fields']);
        $objectBuilder->build();

        return true;
    }

    /**
     * This method receives the array of object fields as an input and adds the fields to the query object building
     *
     * @param QueryObjectClassBuilder $queryObjectBuilder
     * @param string                  $currentTypeName
     * @param array                   $fieldsArray
     */
	private function appendQueryObjectFields(QueryObjectClassBuilder $queryObjectBuilder, string $currentTypeName, array $fieldsArray)
    {
        foreach ($fieldsArray as $fieldArray) {
            $name = $fieldArray['name'];
            // Skip fields with name "query"
            if ($name === 'query') continue;

            $description = $fieldArray['description'] ?? null;
            [$typeName, $typeKind] = $this->getTypeInfo($fieldArray);

            if ($typeKind === FieldTypeKindEnum::SCALAR) {
                $queryObjectBuilder->addScalarField($name, $fieldArray['isDeprecated'], $fieldArray['deprecationReason'], $description);
            } elseif ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
                $this->generateEnumObject($typeName);
                $queryObjectBuilder->addScalarField($name, $fieldArray['isDeprecated'], $fieldArray['deprecationReason'], $description);
            } else {

                // Generate nested type object if it wasn't generated
                $objectGenerated = $this->generateObject($typeName, $typeKind);
                if ($objectGenerated) {

                    // Generate nested type arguments object if it wasn't generated
                    $argsObjectName = $currentTypeName . StringLiteralFormatter::formatUpperCamelCase($name) . 'ArgumentsObject';
                    $argsObjectGenerated = $this->generateArgumentsObject($argsObjectName, $fieldArray['args'] ?? []);
                    if ($argsObjectGenerated) {

                        // Add sub type as a field to the query object if all generation happened successfully
                        $queryObjectBuilder->addObjectField($name, $typeName, $typeKind, $argsObjectName, $fieldArray['isDeprecated'], $fieldArray['deprecationReason'], $description);
                    }
                }
            }
        }
    }

    /**
     * @param string $objectName
     * @param string $objectKind
     *
     * @return bool
     */
    protected function generateObject(string $objectName, string $objectKind): bool
    {
        switch ($objectKind) {
            case FieldTypeKindEnum::OBJECT:
                return $this->generateQueryObject($objectName);
            case FieldTypeKindEnum::INPUT_OBJECT:
                return $this->generateInputObject($objectName);
            case FieldTypeKindEnum::ENUM_OBJECT:
                return $this->generateEnumObject($objectName);
            case FieldTypeKindEnum::UNION_OBJECT:
                return $this->generateUnionObject($objectName);
            case FieldTypeKindEnum::INTERFACE_OBJECT:
                return $this->generateInterfaceObject($objectName);
            default:
                print "Couldn't generate type $objectName: generating $objectKind kind is not supported yet" . PHP_EOL;
                return false;
        }
    }

    /**
     * @param string $objectName
     *
     * @return bool
     */
    protected function generateQueryObject(string $objectName): bool
    {
        if (array_key_exists($objectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$objectName] = true;
        $objectArray   = $this->schemaInspector->getObjectSchema($objectName);
        $objectName    = $objectArray['name'];
        $objectBuilder = new QueryObjectClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);

        $this->appendQueryObjectFields($objectBuilder, $objectName, $objectArray['fields']);
        $objectBuilder->build();

        $resultObjectBuilder = new ResultObjectClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $resultObjectBuilder->setDescription($objectArray['description'] ?? null);
        foreach ($objectArray['interfaces'] ?? [] as $interfaceArray) {
            if ($this->generateObject($interfaceArray['name'], FieldTypeKindEnum::INTERFACE_OBJECT)) {
                $resultObjectBuilder->addInterface($interfaceArray['name']);
            }
        }
        // You'll create this new method, modeled after appendQueryObjectFields
        $this->appendResultObjectFields($resultObjectBuilder, $objectArray['fields']);
        $resultObjectBuilder->build();

        return true;
    }

    /**
     * @param ResultObjectClassBuilder $resultObjectBuilder
     * @param array                    $fieldsArray
     */
    private function appendResultObjectFields(ResultObjectClassBuilder $resultObjectBuilder, array $fieldsArray)
    {
        foreach ($fieldsArray as $fieldArray) {
            $name = $fieldArray['name'];
            if ($name === 'query') continue; // Skip self-referential 'query' field

            [$typeName, $typeKind, $typeKindWrappers] = $this->getTypeInfo($fieldArray);

            // Generate nested objects first, just like the query builder does
            if ($typeKind !== FieldTypeKindEnum::SCALAR && $typeKind !== FieldTypeKindEnum::ENUM_OBJECT) {
                $this->generateObject($typeName, $typeKind);
            }

            // Add the field (property + getter) to the result class
            $resultObjectBuilder->addField($name, $typeName, $typeKind, $typeKindWrappers, $this->possibleTypes[$typeName] ?? [], $fieldArray['description'] ?? null);
        }
    }

    /**
     * @param string $objectName
     *
     * @return bool
     */
    protected function generateInputObject(string $objectName): bool
    {
        if (array_key_exists($objectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$objectName] = true;
        $objectArray   = $this->schemaInspector->getInputObjectSchema($objectName);
        $objectName    = $objectArray['name'];
        $objectBuilder = new InputObjectClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);

        foreach ($objectArray['inputFields'] as $inputFieldArray) {
            $name = $inputFieldArray['name'];
            $description = $inputFieldArray['description'] ?? null;
            //$defaultValue = $inputFieldArray['defaultValue'];
            [$typeName, $typeKind, $typeKindWrappers] = $this->getTypeInfo($inputFieldArray);

            $objectGenerated = true;
            if ($typeKind !== FieldTypeKindEnum::SCALAR) {
                $objectGenerated = $this->generateObject($typeName, $typeKind);
            }

            if ($objectGenerated) {
                if (in_array(FieldTypeKindEnum::LIST, $typeKindWrappers)) {
                    if ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
                        $objectBuilder->addEnumListValue($name, $description);
                    } else {
                        $objectBuilder->addListValue($name, $typeName, $description);
                    }
                } else {
                    if ($typeKind === FieldTypeKindEnum::SCALAR) {
                        $objectBuilder->addScalarValue($name, $description);
                    } elseif ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
                        $objectBuilder->addEnumValue($name, $typeName, $description);
                    } else {
                        $objectBuilder->addInputObjectValue($name, $typeName, $description);
                    }
                }
            }
        }

        $objectBuilder->build();

        return true;
    }

    /**
     * @param string $objectName
     *
     * @return bool
     */
    protected function generateEnumObject(string $objectName): bool
    {
        if (array_key_exists($objectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$objectName] = true;

        $objectArray   = $this->schemaInspector->getEnumObjectSchema($objectName);
        $objectName    = $objectArray['name'];
        $objectBuilder = new EnumObjectBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);

        foreach ($objectArray['enumValues'] as $enumValue) {
            $objectBuilder->addEnumValue($enumValue['name'], $enumValue['description'] ?? null);
        }
        $objectBuilder->build();

        return true;
    }

    /**
     * @param string $objectName
     *
     * @return bool
     */
    protected function generateUnionObject(string $objectName): bool
    {
        if (array_key_exists($objectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$objectName] = true;

        $objectArray   = $this->schemaInspector->getUnionObjectSchema($objectName);
        $objectName    = $objectArray['name'];
        $objectBuilder = new UnionObjectBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);
        $hydratorBuilder = new ResultHydratorClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $this->possibleTypes[$objectName] = array_column($objectArray['possibleTypes'], 'name');

        foreach ($objectArray['possibleTypes'] as $possibleType) {
            $this->generateObject($possibleType['name'], $possibleType['kind']);
            $objectBuilder->addPossibleType($possibleType['name']);
            $hydratorBuilder->addPossibleType($possibleType['name']);
        }
        $objectBuilder->build();
        $hydratorBuilder->build();

        return true;
    }

    /**
     * @param string $objectName
     *
     * @return bool
     */
    protected function generateInterfaceObject(string $objectName): bool
    {
        if (array_key_exists($objectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$objectName] = true;

        $objectArray   = $this->schemaInspector->getInterfaceObjectSchema($objectName);
        $objectName    = $objectArray['name'];
        $objectBuilder = new InterfaceObjectClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $objectBuilder->setDescription($objectArray['description'] ?? null);
        $hydratorBuilder = new ResultHydratorClassBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix, $this->classPrefix . $objectName);
        $this->possibleTypes[$objectName] = array_column($objectArray['possibleTypes'] ?? [], 'name');

        $this->appendQueryObjectFields($objectBuilder, $objectName, $objectArray['fields'] ?? []);
        foreach ($objectArray['possibleTypes'] ?? [] as $possibleType) {
            if ($this->generateObject($possibleType['name'], $possibleType['kind'])) {
                $objectBuilder->addPossibleType($possibleType['name']);
                $hydratorBuilder->addPossibleType($possibleType['name']);
            }
        }
        $objectBuilder->build();
        $hydratorBuilder->build();

        // The PHP interface implemented by the result classes of the possible types
        $resultInterfaceBuilder = new ResultInterfaceBuilder($this->writeDir, $objectName, $this->generationNamespace, $this->classPrefix);
        $resultInterfaceBuilder->setDescription($objectArray['description'] ?? null);
        foreach ($objectArray['interfaces'] ?? [] as $interfaceArray) {
            if ($this->generateObject($interfaceArray['name'], FieldTypeKindEnum::INTERFACE_OBJECT)) {
                $resultInterfaceBuilder->addInterface($interfaceArray['name']);
            }
        }
        $this->appendResultObjectFields($resultInterfaceBuilder, $objectArray['fields'] ?? []);
        $resultInterfaceBuilder->build();

        return true;
    }

    /**
     * @param string $argsObjectName
     * @param array  $arguments
     *
     * @return bool
     */
    protected function generateArgumentsObject(string $argsObjectName, array $arguments): bool
    {
        if (array_key_exists($argsObjectName, $this->generatedObjects)) {
            return true;
        }

        $this->generatedObjects[$argsObjectName] = true;

        $objectBuilder = new ArgumentsObjectClassBuilder($this->writeDir, $argsObjectName, $this->generationNamespace, $this->classPrefix);

        foreach ($arguments as $argumentArray) {
            $name = $argumentArray['name'];
            $description = $argumentArray['description'] ?? null;
            //$defaultValue = $inputFieldArray['defaultValue'];
            [$typeName, $typeKind, $typeKindWrappers] = $this->getTypeInfo($argumentArray);

            $objectGenerated = true;
            if ($typeKind !== FieldTypeKindEnum::SCALAR) {
                $objectGenerated = $this->generateObject($typeName, $typeKind);
            }

            if ($objectGenerated) {
                if (in_array(FieldTypeKindEnum::LIST, $typeKindWrappers)) {
                    if ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
                        $objectBuilder->addEnumListArgument($name, $description);
                    } else {
                        $objectBuilder->addListArgument($name, $typeName, $description);
                    }
                } else {
                    if ($typeKind === FieldTypeKindEnum::SCALAR) {
                        $objectBuilder->addScalarArgument($name, $description);
                    } elseif ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
                        $objectBuilder->addInputEnumArgument($name, $typeName, $description);
                    } else {
                        $objectBuilder->addInputObjectArgument($name, $typeName, $description);
                    }
                }
            }
        }
        $objectBuilder->build();

        return true;
    }

    /**
     * @param array $dataArray : The subarray which contains the key "type"
     *
     * @return array : Array formatted as [$typeName, $typeKind, $typeKindWrappers]
     */
    protected function getTypeInfo(array $dataArray): array
    {
        $typeArray = $dataArray['type'];
        $typeWrappers = [];
        while ($typeArray['ofType'] !== null) {
            $typeWrappers[] = $typeArray['kind'];
            $typeArray = $typeArray['ofType'];

            // Throw exception if next array doesn't have ofType key
            if (!array_key_exists('ofType', $typeArray)) {
                throw new RuntimeException('Reached the limit of nesting in type info');
            }
        }
        $typeInfo = [$typeArray['name'], $typeArray['kind'], $typeWrappers];

        return $typeInfo;
    }

    /**
     * Sets the write directory if it's not set for the class
     */
	private function setWriteDir(): void
    {
        if ($this->writeDir !== '') return;

        $currentDir = dirname(__FILE__);
        while (basename($currentDir) !== 'php-graphql-oqm') {
            $currentDir = dirname($currentDir);
        }

        $this->writeDir = $currentDir . '/schema_object';
    }

    /**
     * @return string
     */
    public function getWriteDir(): string
    {
        $this->setWriteDir();

        return $this->writeDir;
    }


    public function getClassPrefix(): string
    {
        return $this->classPrefix;
    }

    public function setClassPrefix(string $classPrefix): void
    {
        $this->classPrefix = $classPrefix;
    }
}
