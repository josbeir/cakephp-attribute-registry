<?php
//phpcs:ignoreFile

declare(strict_types=1);

namespace AttributeRegistry\Test\Data;

#[TestRoute('/users', 'GET')]
#[TestConfig(
    enabled: true,
    name: null,
    options: ['debug' => true, 'cache' => false]
)]
class TestController
{
    #[TestConst(
        description: 'Active status',
        deprecated: false
    )]
    public const STATUS_ACTIVE = 'active';

    #[TestColumn('int', 11)]
    public int $id;

    #[TestColumn('string', 255)]
    public string $name;

    #[TestGet('/')]
    public function index(): void
    {
    }

    #[TestGet('/show')]
    public function show(
        #[TestParam(source: 'path', name: 'id')] int $id
    ): void
    {
    }

    public function withoutAttribute(): void
    {
    }

    #[TestConfig(enabled: false)]
    public function disabled(): void
    {
    }
}


#[TestWithEnum(
    label: 'Text Transformer',
    category: TestCategory::Text,
    priority: TestPriority::High
)]
class TestTransformer
{
}

/**
 * Test class demonstrating object instance in attribute argument.
 */
#[TestWithObject(
    argument: new TestAttributeArgument('test-value', 42),
    label: 'Object Argument Test'
)]
class TestWithObjectArgument
{
    #[TestWithObject(argument: new TestAttributeArgument('method-object'))]
    public function methodWithObject(): void
    {
    }
}

/**
 * Test class demonstrating array of object instances in attribute argument.
 */
#[TestWithObjectArray(
    arguments: [
        new TestAttributeArgument('first', 1),
        new TestAttributeArgument('second', 2),
        new TestAttributeArgument('third'),
    ],
    description: 'Multiple object arguments'
)]
class TestWithObjectArrayArgument
{
}
