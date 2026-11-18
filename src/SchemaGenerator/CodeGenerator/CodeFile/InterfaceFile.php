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
}
