<?php

namespace GraphQL\Tests;

use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\ResultObjectClassBuilder;

class ResultObjectClassBuilderTest extends CodeFileTestCase
{
    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\ResultObjectClassBuilder::addField
     */
    public function testEnumFieldsAreTypedAsTheirStringValue()
    {
        $builder = new ResultObjectClassBuilder(static::getGeneratedFilesDir(), 'WithEnums', 'GraphQL\\Tests\\SchemaObject');
        $builder->addField('status', 'Status', FieldTypeKindEnum::ENUM_OBJECT, []);
        $builder->addField('statuses', 'Status', FieldTypeKindEnum::ENUM_OBJECT, [FieldTypeKindEnum::LIST]);
        $builder->build();

        $contents = file_get_contents(static::getGeneratedFilesDir() . '/WithEnums.php');
        $this->assertStringContainsString("     * @return string A StatusEnumObject value\n     */\n    public function getStatus(): ?string", $contents);
        $this->assertStringContainsString("     * @return string[] StatusEnumObject values\n     */\n    public function getStatuses(): ?array", $contents);
    }

    /**
     * @covers \GraphQL\SchemaGenerator\CodeGenerator\ResultObjectClassBuilder::addField
     */
    public function testNativeReturnTypes()
    {
        $builder = new ResultObjectClassBuilder(static::getGeneratedFilesDir(), 'WithTypes', 'GraphQL\\Tests\\SchemaObject');
        $builder->addField('title', 'String', FieldTypeKindEnum::SCALAR, []);
        $builder->addField('count', 'Int', FieldTypeKindEnum::SCALAR, [FieldTypeKindEnum::NON_NULL]);
        $builder->addField('meta', 'JSON', FieldTypeKindEnum::SCALAR, []);
        $builder->addField('updatedAt', 'DateTime', FieldTypeKindEnum::SCALAR, []);
        $builder->addField('product', 'Product', FieldTypeKindEnum::OBJECT, []);
        $builder->addField('pinned', 'Pinned', FieldTypeKindEnum::UNION_OBJECT, [], ['Product', 'Article']);
        $builder->build();

        $contents = file_get_contents(static::getGeneratedFilesDir() . '/WithTypes.php');
        $this->assertStringContainsString('public function getTitle(): ?string', $contents);
        $this->assertStringContainsString('public function getCount(): ?int', $contents);
        $this->assertStringContainsString('public function getMeta(): mixed', $contents);
        $this->assertStringContainsString('public function getUpdatedAt(): ?Carbon', $contents);
        $this->assertStringContainsString('public function getProduct(): ?Product', $contents);
        $this->assertStringContainsString('public function getPinned(): Product|Article|null', $contents);
        $this->assertStringContainsString('PinnedHydrator::fromArray($data[\'pinned\'])', $contents);
    }
}
