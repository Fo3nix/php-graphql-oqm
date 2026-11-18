<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;

/**
 * Class ObjectClassBuilder
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator
 */
abstract class ObjectClassBuilder implements ObjectBuilderInterface
{
    /**
     * @var ClassFile
     */
    protected $classFile;

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
     * Lists the arguments or input fields the schema requires, checked before the request is sent
     *
     * @param string[] $names
     */
    public function setRequiredValues(array $names)
    {
        if (!empty($names)) {
            $this->classFile->addConstant('REQUIRED', array_values($names));
        }
    }

    /**
     * @param string $propertyName
     */
    protected function addProperty($propertyName)
    {
        $this->classFile->addProperty($propertyName);
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     * @param string|null $description
     */
    protected function addScalarSetter($propertyName, $upperCamelName, ?string $description = null)
    {
        $lowerCamelName = lcfirst($upperCamelName);
        $method = "public function set$upperCamelName($$lowerCamelName)
{
    \$this->$propertyName = $$lowerCamelName;

    return \$this;
}";
        $this->classFile->addMethod($method, false, null, $description);
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     * @param string $propertyType
     * @param string|null $description
     */
    protected function addListSetter(string $propertyName, string $upperCamelName, string $propertyType, ?string $description = null)
    {
        $lowerCamelName = lcfirst($upperCamelName);
        $method = "public function set$upperCamelName(array $$lowerCamelName)
{
    \$this->$propertyName = $$lowerCamelName;

    return \$this;
}";
        $this->classFile->addMethod($method, false, null, $description);
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     * @param string $objectClass
     * @param string|null $description
     */
    protected function addEnumSetter(string $propertyName, string $upperCamelName, string $objectClass, ?string $description = null)
    {
        $lowerCamelName = lcfirst(str_replace('_', '', $objectClass));
        $method         = "public function set$upperCamelName($$lowerCamelName)
{
    \$this->$propertyName = new RawObject($$lowerCamelName);

    return \$this;
}";
        $this->classFile->addMethod($method, false, null, $description);
        $this->classFile->addImport('GraphQL\\RawObject');
    }

    /**
     * Enum values are wrapped in RawObjects so they are written unquoted in the query
     *
     * @param string $propertyName
     * @param string $upperCamelName
     * @param string|null $description
     */
    protected function addEnumListSetter(string $propertyName, string $upperCamelName, ?string $description = null)
    {
        $lowerCamelName = lcfirst($upperCamelName);
        $method = "public function set$upperCamelName(array $$lowerCamelName)
{
    \$this->$propertyName = array_map(function (\$value) {
        return new RawObject(\$value);
    }, $$lowerCamelName);

    return \$this;
}";
        $this->classFile->addMethod($method, false, null, $description);
        $this->classFile->addImport('GraphQL\\RawObject');
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     * @param string $objectClass
     * @param string|null $description
     */
    protected function addObjectSetter(string $propertyName, string $upperCamelName, string $objectClass, ?string $description = null)
    {
        $lowerCamelName = lcfirst(str_replace('_', '', $objectClass));
        $method         = "public function set$upperCamelName($objectClass $$lowerCamelName)
{
    \$this->$propertyName = $$lowerCamelName;

    return \$this;
}";
        $this->classFile->addMethod($method, false, null, $description);
    }
}