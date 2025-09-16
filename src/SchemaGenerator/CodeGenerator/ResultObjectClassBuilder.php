<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\Util\StringLiteralFormatter;

class ResultObjectClassBuilder extends ObjectClassBuilder
{
    private $generationNamespace;
    private $fields = []; // Stores field info for the hydrator

    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE)
    {
        $this->classFile = new ClassFile($writeDir, $objectName);
        $this->classFile->setNamespace($namespace);
        $this->generationNamespace = $namespace;
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
            case FieldTypeKindEnum::UNION_OBJECT:
                return $typeName; // e.g., "Pokemon"

            case FieldTypeKindEnum::ENUM_OBJECT:
                return $typeName . 'EnumObject';

            default:
                return 'mixed';
        }
    }

    /**
     * Adds a field as a property and a fully type-hinted getter.
     */
    public function addField(string $fieldName, string $typeName, string $typeKind, array $typeWrappers)
    {
        $this->classFile->addProperty($fieldName);

        // 1. Get base type (now passes $fieldName)
        $basePhpType = $this->mapGraphQLToPHPType($typeName, $typeKind, $fieldName);

        $isList = in_array(FieldTypeKindEnum::LIST, $typeWrappers);
        $isObject = in_array($typeKind, [FieldTypeKindEnum::OBJECT, FieldTypeKindEnum::UNION_OBJECT]);
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

        // 4. Construct PHPDoc
        $docReturnType = $docType . ($isList ? '[]' : '');

        // 5. Store info for hydrator
        $this->fields[$fieldName] = [
            'type' => $docType,
            'isList' => $isList,
            'isObject' => $isObject,
            'isCarbon' => $isCarbon,
        ];

        // 6. Generate the getter method
        $upperCamelField = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $method = "
/**
 * @return " . $docReturnType . "
 */
public function get" . $upperCamelField . "()
{
    return \$this->" . $fieldName . ";
}";

        $this->classFile->addMethod($method);
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
                if ($info['isList']) {
                    $lines[] = "            \$instance->$fieldName = array_map(function(\$item) { return " . $info['type'] . "::fromArray(\$item); }, \$data['$fieldName']);";
                } else {
                    $lines[] = "            \$instance->$fieldName = " . $info['type'] . "::fromArray(\$data['$fieldName']);";
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

    // You must call this *after* all fields are added
    public function build(): void
    {
        $this->classFile->addMethod($this->generateHydrationMethod());
        $this->classFile->writeFile();
    }
}