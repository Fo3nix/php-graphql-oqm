<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;

/**
 * Builds the hydrator of a GraphQL interface or union. The concrete type of such a field is only known at runtime, so
 * the hydrator picks the result class to hydrate by the __typename of the data.
 *
 * Class ResultHydratorClassBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
class ResultHydratorClassBuilder implements ObjectBuilderInterface
{
    /**
     * @var ClassFile
     */
    protected $classFile;

    /**
     * @var string GraphQL name of the interface or union
     */
    private $typeName;

    /**
     * @var string|null Type of the hydrated objects
     */
    private $returnType;

    /**
     * @var string[] GraphQL names of the possible types
     */
    private $possibleTypes = [];

    private $classPrefix;

    /**
     * @param string      $writeDir
     * @param string      $typeName
     * @param string      $namespace
     * @param string      $classPrefix
     * @param string|null $returnType Type of the hydrated objects, defaults to the union of the possible types
     */
    public function __construct(string $writeDir, string $typeName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '', ?string $returnType = null)
    {
        $this->classFile = new ClassFile($writeDir, $classPrefix . $typeName . 'Hydrator');
        $this->classFile->setNamespace($namespace);
        $this->typeName = $typeName;
        $this->classPrefix = $classPrefix;
        $this->returnType = $returnType;
    }

    /**
     * @param string $typeName
     */
    public function addPossibleType(string $typeName)
    {
        $this->possibleTypes[] = $typeName;
    }

    public function build(): void
    {
        $classPrefix = $this->classPrefix;
        $classNames = array_map(function ($possibleType) use ($classPrefix) {
            return ResultObjectClassBuilder::className($classPrefix, $possibleType);
        }, $this->possibleTypes);
        $returnType = $this->returnType ?? (empty($classNames) ? 'object' : implode('|', $classNames));

        $cases = '';
        $instanceChecks = '';
        foreach ($this->possibleTypes as $index => $possibleType) {
            $className = $classNames[$index];
            $cases .= "
        case '$possibleType':
            return $className::fromArray(\$data);";
            $instanceChecks .= "
    if (\$object instanceof $className) {
        return ['__typename' => '$possibleType'] + \$object->asArray();
    }";
        }

        $this->classFile->addMethod("/**
 * Hydrates the result class matching the __typename of the data
 *
 * @param array \$data
 * @return $returnType
 */
public static function fromArray(array \$data): $returnType
{
    if (!isset(\$data['__typename'])) {
        throw new \\InvalidArgumentException('Cannot hydrate {$this->typeName}: the data has no __typename, select it in the query');
    }
    switch (\$data['__typename']) {{$cases}
        default:
            throw new \\InvalidArgumentException('Cannot hydrate {$this->typeName}: unknown type \"' . \$data['__typename'] . '\", regenerate the schema objects');
    }
}");
        $this->classFile->addMethod("/**
 * Converts a hydrated object to an array, including its __typename so it can be hydrated again
 *
 * @param $returnType \$object
 * @return array
 */
public static function toArray($returnType \$object): array
{{$instanceChecks}
    throw new \\InvalidArgumentException('Cannot convert ' . get_class(\$object) . ' to an array of {$this->typeName}');
}");
        $this->classFile->writeFile();
    }
}
