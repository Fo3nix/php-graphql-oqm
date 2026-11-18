<?php

namespace GraphQL\SchemaGenerator\CodeGenerator\CodeFile;

use GraphQL\Util\StringLiteralFormatter;

/**
 * Class TraitFile
 *
 * @package GraphQL\SchemaManager\CodeGenerator\CodeFile
 */
class TraitFile extends AbstractCodeFile
{
    /**
     * This string constant stores the file structure in a format that can be used with sprintf
     *
     * @var string
     */
    protected const FILE_FORMAT = '<?php
%1$s%2$s
trait %3$s
{%4$s%5$s}
';

    /**
     * This string stores the name of the namespace which this class belongs to
     *
     * @var string
     */
    protected $namespace;

    /**
     * This array is a list that stores the fully qualified class names that need to be imported with "use" statements
     *
     * @var array
     */
    protected $imports;

    /**
     * This array is a map that stores that properties defined in a file in a key value manner [propertyName] => value
     *
     * @var array
     */
    protected $properties;

    /**
     * This array is a list that stores the string representations of methods in the file
     *
     * @var array
     */
    protected $methods;

    /**
     * The formatted comment documenting the declared class, trait or interface
     *
     * @var string
     */
    protected $docComment = '';

    /**
     * TraitFile constructor.
     *
     * @param $writeDir
     * @param $fileName
     */
    public function __construct(string $writeDir, string $fileName)
    {
        parent::__construct($writeDir, $fileName);
        $this->namespace  = '';
        $this->imports    = [];
        $this->properties = [];
        $this->methods    = [];
    }

    /**
     * @param $namespaceName
     */
    public function setNamespace(string $namespaceName)
    {
        if (!empty($namespaceName)) {
            $this->namespace = $namespaceName;
        }
    }

    /**
     * @param string $fullyQualifiedName
     */
    public function addImport(string $fullyQualifiedName)
    {
        if (!empty($fullyQualifiedName)) {
            $this->imports[$fullyQualifiedName] = null;
        }
    }

    /**
     * @param string               $name
     * @param null|string|int|bool $value
     */
    public function addProperty(string $name, $value = null)
    {
        if (is_string($name) && !empty($name)) {
            $this->properties[$name] = $value;
        }
    }

    /**
     * @param string $methodString
     * @param bool $isDeprecated
     * @param string|null $deprecationReason
     */
    public function addMethod(string $methodString, bool $isDeprecated = false, ?string $deprecationReason = null, ?string $description = null)
    {
        if (!empty($methodString)) {
            $methodString = $this->prependDocComment($methodString, $isDeprecated, $deprecationReason, $description);

            // Normalize line endings here to make replacements in generateMethods() work.
            $methodString = $this->normalizeLineEndings($methodString);
            $this->methods[] = $methodString;
        }
    }

    /**
     * @inheritdoc
     */
    protected function generateFileContents(): string
    {
        $className = $this->generateClassName();

        // Generate class headers
        $namespace = $this->generateNamespace();
        if (!empty($namespace)) $namespace = PHP_EOL . $namespace;
        $imports = $this->generateImports();
        if (!empty($imports)) $imports = PHP_EOL . $imports;
        $imports = $this->appendDocComment($imports);

        // Generate class body
        $properties = $this->generateProperties();
        if (!empty($properties)) $properties = PHP_EOL . $properties;
        $methods = $this->generateMethods();

        $contents = sprintf(static::FILE_FORMAT, $namespace, $imports, $className, $properties, $methods);
        return $this->normalizeLineEndings($contents);
    }

    /**
     * @return string The declared name, including any inheritance clause
     */
    protected function generateClassName(): string
    {
        return $this->fileName;
    }

    /**
     * @return string
     */
    protected function generateNamespace(): string
    {
        $string = '';
        if (!empty($this->namespace)) {
            $string = "namespace $this->namespace;" . PHP_EOL;
        }

        return $string;
    }

    /**
     * @return string
     */
    protected function generateImports(): string
    {
        $string = '';
        if (!empty($this->imports)) {
            foreach ($this->imports as $import => $nothing) {
                $string .= "use $import;" . PHP_EOL;
            }
        }

        return $string;
    }

    /**
     * @return string
     */
    protected function generateProperties(): string
    {
        $string = '';
        if (!empty($this->properties)) {
            foreach ($this->properties as $name => $value) {
                if ($value === null) {
                    $string .= "    protected $$name;" . PHP_EOL;
                } else {
                    $value = $this->serializeParameterValue($value);
                    $string .= "    protected $$name = $value;" . PHP_EOL;
                }
            }
        }

        return $string;
    }

    /**
     * @return string
     */
    protected function generateMethods(): string
    {
        $string = '';
        if (!empty($this->methods)) {
            foreach ($this->methods as $method) {
                // Indent method with 4 space characters
                $method = str_replace(PHP_EOL, PHP_EOL . '    ', $method);
                // Make lines that were previously empty, empty again.
                $method = str_replace(PHP_EOL . '    ' . PHP_EOL, PHP_EOL . PHP_EOL, $method);
                $string .= PHP_EOL . '    ' . $method . PHP_EOL;
            }
        }

        return $string;
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function serializeParameterValue($value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map([$this, 'serializeParameterValue'], $value)) . ']';
        }

        return StringLiteralFormatter::formatValueForRHS($value);
    }

    /**
     * @param string $code
     * @param bool $isDeprecated
     * @param string|null $deprecationReason
     *
     * @return string
     */
    protected function prependDocComment(string $code, bool $isDeprecated, ?string $deprecationReason, ?string $description = null): string
    {
        $tags = $isDeprecated ? ['@deprecated' . ($deprecationReason ? " $deprecationReason" : '')] : [];

        return DocComment::format($description, $tags) . $code;
    }

    /**
     * Sets the comment documenting the class, trait or interface itself
     *
     * @param string|null $description
     */
    public function setDocComment(?string $description)
    {
        $this->docComment = DocComment::format($description);
    }

    /**
     * Appends the doc comment of the file's class to its imports, the part of the file right above the declaration
     *
     * @param string $imports
     *
     * @return string
     */
    protected function appendDocComment(string $imports): string
    {
        if ($this->docComment === '') {
            return $imports;
        }

        return $imports . PHP_EOL . rtrim($this->docComment, PHP_EOL);
    }

    protected function normalizeLineEndings(string $code)
    {
        $code = str_replace("\r", '', $code);
        if (PHP_EOL !== "\n") {
            $code = str_replace("\n", PHP_EOL, $code);
        }

        return $code;
    }
}
