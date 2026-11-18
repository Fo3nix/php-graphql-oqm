<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\DocComment;
use GraphQL\Util\StringLiteralFormatter;

class ResultObjectClassBuilder extends ObjectClassBuilder
{
    protected $generationNamespace;
    private $fields = []; // Stores field info for the hydrator
    protected $classPrefix = '';

    /**
     * Words PHP reserves as class names (case-insensitive)
     */
    private const RESERVED_CLASS_NAMES = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally',
        'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
        'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace',
        'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly',
        'require', 'require_once', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try',
        'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    /**
     * The name of the result class (or PHP interface) generated for a GraphQL type. Result classes are named like the
     * type, so a name PHP reserves (e.g. the type "Return") gets a trailing underscore.
     *
     * @param string $classPrefix
     * @param string $typeName
     *
     * @return string
     */
    public static function className(string $classPrefix, string $typeName): string
    {
        $className = $classPrefix . $typeName;
        if (in_array(strtolower($className), self::RESERVED_CLASS_NAMES, true)) {
            $className .= '_';
        }

        return $className;
    }

    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE, string $classPrefix = '')
    {
        $this->classFile = new ClassFile($writeDir, self::className($classPrefix, $objectName));
        $this->classFile->setNamespace($namespace);
        $this->generationNamespace = $namespace;
        $this->classPrefix = $classPrefix;
    }

    /**
     * Maps a GraphQL type to a PHP type name (scalar or class name).
     */
    private function mapGraphQLToPHPType(string $typeName, string $typeKind, string $fieldName): string
    {
        switch ($typeKind) {
            case FieldTypeKindEnum::SCALAR:
                // Strategy 1: Check for custom date/time scalars
                if (in_array($typeName, ['DateTime', 'Date', 'Timestamp'])) {
                    return '\Carbon\Carbon';
                }

                // Strategy 2: Check string fields by name convention
                if ($typeName === 'String') {
                    if (preg_match('/(_at|At|Date)$/', $fieldName)) {
                        return '\Carbon\Carbon';
                    }
                    return 'string';
                }

                // Other scalars
                if ($typeName === 'Int') return 'int';
                if ($typeName === 'Float') return 'float';
                if ($typeName === 'Boolean') return 'bool';
                return 'string'; // Default for ID and other scalars

            case FieldTypeKindEnum::OBJECT:
            case FieldTypeKindEnum::INTERFACE_OBJECT:
                return self::className($this->classPrefix, $typeName); // e.g., "Pokemon"

            case FieldTypeKindEnum::ENUM_OBJECT:
                return $this->classPrefix . $typeName . 'EnumObject';

            default:
                return 'mixed';
        }
    }

    /**
     * Maps a non-list GraphQL type to a native PHP return type. Every type is nullable because fields that were not
     * selected in the query are null. Custom scalars can be serialized as anything (e.g. a JSON scalar), so they are
     * not narrowed.
     *
     * @param string $typeName
     * @param string $typeKind
     * @param string $docType The PHPDoc type of the field
     *
     * @return string
     */
    private function mapGraphQLToNativeType(string $typeName, string $typeKind, string $docType): string
    {
        switch ($typeKind) {
            case FieldTypeKindEnum::OBJECT:
            case FieldTypeKindEnum::INTERFACE_OBJECT:
                return '?' . $docType;
            case FieldTypeKindEnum::ENUM_OBJECT:
                // Enum values are hydrated as their string value
                return '?string';
            case FieldTypeKindEnum::SCALAR:
                if ($docType === 'Carbon') {
                    return '?Carbon';
                }
                $scalarTypes = ['String' => '?string', 'ID' => '?string', 'Int' => '?int', 'Float' => '?float', 'Boolean' => '?bool'];

                return $scalarTypes[$typeName] ?? 'mixed';
            default:
                return 'mixed';
        }
    }

    /**
     * Declares that this result class implements the PHP interface generated for a GraphQL interface
     *
     * @param string $interfaceName
     */
    public function addInterface(string $interfaceName)
    {
        $this->classFile->implementsInterface(self::className($this->classPrefix, $interfaceName));
    }

    /**
     * Resolves how a field is typed, documented and hydrated, and adds the imports it needs.
     *
     * @param string   $fieldName
     * @param string   $typeName
     * @param string   $typeKind
     * @param array    $typeWrappers
     * @param string[] $possibleTypes Names of the types a union field can hold
     *
     * @return array
     */
    protected function resolveField(string $fieldName, string $typeName, string $typeKind, array $typeWrappers, array $possibleTypes): array
    {
        $isList = in_array(FieldTypeKindEnum::LIST, $typeWrappers);
        $isObject = in_array($typeKind, [FieldTypeKindEnum::OBJECT, FieldTypeKindEnum::UNION_OBJECT, FieldTypeKindEnum::INTERFACE_OBJECT]);

        // Interfaces and unions are hydrated by a hydrator class that picks the concrete type by __typename
        $hydrator = null;
        if ($typeKind === FieldTypeKindEnum::INTERFACE_OBJECT || $typeKind === FieldTypeKindEnum::UNION_OBJECT) {
            $hydrator = $this->classPrefix . $typeName . 'Hydrator';
            $this->classFile->addImport($this->generationNamespace . '\\' . $hydrator);
        }

        if ($typeKind === FieldTypeKindEnum::UNION_OBJECT) {
            $classPrefix = $this->classPrefix;
            $docType = empty($possibleTypes) ? 'object' : implode('|', array_map(function ($possibleType) use ($classPrefix) {
                return ResultObjectClassBuilder::className($classPrefix, $possibleType);
            }, $possibleTypes));

            $docReturnType = $docType;
            if ($isList) {
                $docReturnType = (count($possibleTypes) > 1 ? "($docType)" : $docType) . '[]';
            }

            if ($isList) {
                $nativeType = '?array';
            } elseif (empty($possibleTypes)) {
                $nativeType = 'mixed';
            } elseif (count($possibleTypes) === 1) {
                $nativeType = '?' . $docType;
            } else {
                $nativeType = $docType . '|null';
            }

            return [
                'type' => $docType,
                'docType' => $docReturnType,
                'nativeType' => $nativeType,
                'isList' => $isList,
                'isObject' => true,
                'isCarbon' => false,
                'hydrator' => $hydrator,
            ];
        }

        // 1. Get base type (now passes $fieldName)
        $basePhpType = $this->mapGraphQLToPHPType($typeName, $typeKind, $fieldName);
        $isCarbon = ($basePhpType === '\Carbon\Carbon');

        // 3. Handle imports
        $docType = $basePhpType;
        if ($isCarbon) {
            $this->classFile->addImport('Carbon\Carbon');
            $docType = 'Carbon'; // Use the short name for PHPDoc
        }
        if ($isObject) {
            $this->classFile->addImport($this->generationNamespace . '\\' . $basePhpType);
        }

        // Enum values are hydrated as their string value, the enum class lists the possible values
        $docReturnType = $docType . ($isList ? '[]' : '');
        if ($typeKind === FieldTypeKindEnum::ENUM_OBJECT) {
            $docReturnType = ($isList ? "string[] $basePhpType values" : "string A $basePhpType value");
        }

        return [
            'type' => $docType,
            'docType' => $docReturnType,
            'nativeType' => $isList ? '?array' : $this->mapGraphQLToNativeType($typeName, $typeKind, $docType),
            'isList' => $isList,
            'isObject' => $isObject,
            'isCarbon' => $isCarbon,
            'hydrator' => $hydrator,
        ];
    }

    /**
     * Adds a field as a property and a fully type-hinted getter.
     *
     * @param string   $fieldName
     * @param string   $typeName
     * @param string   $typeKind
     * @param array    $typeWrappers
     * @param string[]    $possibleTypes Names of the types a union field can hold
     * @param string|null $description
     */
    public function addField(string $fieldName, string $typeName, string $typeKind, array $typeWrappers, array $possibleTypes = [], ?string $description = null)
    {
        $this->classFile->addProperty($fieldName);

        // Store info for hydrator
        $info = $this->resolveField($fieldName, $typeName, $typeKind, $typeWrappers, $possibleTypes);
        $this->fields[$fieldName] = $info;

        $this->classFile->addMethod($this->generateGetter($fieldName, $info['docType'], $info['nativeType'], true, $description));
    }

    /**
     * @param string $fieldName
     * @param string $docReturnType
     * @param string $nativeReturnType
     * @param bool   $withBody False to only declare the getter, as an interface does
     * @param string|null $description
     *
     * @return string
     */
    protected function generateGetter(string $fieldName, string $docReturnType, string $nativeReturnType, bool $withBody = true, ?string $description = null): string
    {
        $upperCamelField = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $method = "
" . DocComment::format($description, ["@return $docReturnType"]) . "public function get" . $upperCamelField . "(): " . $nativeReturnType;

        if (!$withBody) {
            return $method . ';';
        }

        return $method . "
{
    return \$this->" . $fieldName . ";
}";
    }

    private function generateHydrationMethod(): string
    {
        $lines = [
            "    /**",
            "     * @param array \$data",
            "     * @return self",
            "     */",
            "    public static function fromArray(array \$data): self",
            "    {",
            "        \$instance = new self();",
        ];

        foreach ($this->fields as $fieldName => $info) {
            $lines[] = "        if (isset(\$data['$fieldName']) && \$data['$fieldName'] !== null) {";

            if ($info['isObject']) {
                $hydrator = $info['hydrator'] ?? $info['type'];
                if ($info['isList']) {
                    $lines[] = "            \$instance->$fieldName = array_map(function(\$item) { return " . $hydrator . "::fromArray(\$item); }, \$data['$fieldName']);";
                } else {
                    $lines[] = "            \$instance->$fieldName = " . $hydrator . "::fromArray(\$data['$fieldName']);";
                }
            }
            // --- NEW SECTION ---
            else if ($info['isCarbon']) {
                // Ensure Carbon is imported for this method
                $this->classFile->addImport('Carbon\Carbon');
                if ($info['isList']) {
                    $lines[] = "            \$instance->$fieldName = array_map(function(\$item) { return new Carbon(\$item); }, \$data['$fieldName']);";
                } else {
                    $lines[] = "            \$instance->$fieldName = new Carbon(\$data['$fieldName']);";
                }
            }
            // --- END NEW SECTION ---
            else {
                // Scalar or Enum
                $lines[] = "            \$instance->$fieldName = \$data['$fieldName'];";
            }
            $lines[] = "        }";
        }

        $lines[] = "        return \$instance;";
        $lines[] = "    }";

        return implode(PHP_EOL, $lines);
    }

    private function generateJsonHydrationMethod(): string
    {
        $lines = [
            "    /**",
            "     * @param string \$json",
            "     * @return self",
            "     */",
            "    public static function fromJson(string \$json): self",
            "    {",
            "        \$data = json_decode(\$json, true);",
            "        if (\$data === null && json_last_error() !== JSON_ERROR_NONE) {",
            "            throw new \InvalidArgumentException('Invalid JSON provided to fromJson method: ' . json_last_error_msg());",
            "        }",
            "        return self::fromArray(\$data);",
            "    }",
        ];

        return implode(PHP_EOL, $lines);
    }

    private function generateAsArrayMethod(): string
    {
        $lines = [
            "    /**",
            "     * Converts this object to an array.",
            "     * @return array",
            "     */",
            "    public function asArray(): array", // <-- Renamed
            "    {",
            "        \$data = [];",
        ];

        foreach ($this->fields as $fieldName => $info) {
            $lines[] = "        if (\$this->$fieldName !== null) {";

            if ($info['isCarbon']) {
                if ($info['isList']) {
                    $lines[] = "            \$data['$fieldName'] = array_map(function(\$item) { return \$item->toIso8601String(); }, \$this->$fieldName);";
                } else {
                    $lines[] = "            \$data['$fieldName'] = \$this->$fieldName" . "->toIso8601String();";
                }
            }
            else if ($info['hydrator'] !== null) {
                // Keep __typename so the array can be hydrated again
                if ($info['isList']) {
                    $lines[] = "            \$data['$fieldName'] = array_map(function(\$item) { return " . $info['hydrator'] . "::toArray(\$item); }, \$this->$fieldName);";
                } else {
                    $lines[] = "            \$data['$fieldName'] = " . $info['hydrator'] . "::toArray(\$this->$fieldName);";
                }
            }
            else if ($info['isObject']) {
                if ($info['isList']) {
                    // Recursively call asArray()
                    $lines[] = "            \$data['$fieldName'] = array_map(function(\$item) { return \$item->asArray(); }, \$this->$fieldName);";
                } else {
                    // Recursively call asArray()
                    $lines[] = "            \$data['$fieldName'] = \$this->$fieldName" . "->asArray();";
                }
            }
            else {
                // Scalars and Enums
                $lines[] = "            \$data['$fieldName'] = \$this->$fieldName;";
            }
            $lines[] = "        }";
        }

        $lines[] = "        return \$data;";
        $lines[] = "    }";

        return implode(PHP_EOL, $lines);
    }

    // You must call this *after* all fields are added
    public function build(): void
    {
        $this->classFile->addMethod($this->generateHydrationMethod());
        $this->classFile->addMethod($this->generateJsonHydrationMethod());
        $this->classFile->addMethod($this->generateAsArrayMethod());
        $this->classFile->writeFile();
    }
}