<?php

namespace GraphQL\SchemaGenerator\CodeGenerator\CodeFile;

/**
 * Class InterfaceFile
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator\CodeFile
 */
class InterfaceFile extends TraitFile
{
    /**
     * This string constant stores the file structure in a format that can be used with sprintf
     *
     * @var string
     */
    protected const FILE_FORMAT = '<?php
%1$s%2$s
interface %3$s
{%4$s%5$s}
';

    /**
     * The list of interfaces extended by this interface
     *
     * @var array
     */
    protected $parentInterfaces = [];

    /**
     * @param string $interfaceName
     */
    public function extendsInterface(string $interfaceName)
    {
        if (!empty($interfaceName)) {
            $this->parentInterfaces[$interfaceName] = null;
        }
    }

    /**
     * @inheritdoc
     */
    protected function generateClassName(): string
    {
        $string = $this->fileName;
        if (!empty($this->parentInterfaces)) {
            $string .= ' extends ' . implode(', ', array_keys($this->parentInterfaces));
        }

        return $string;
    }
}
