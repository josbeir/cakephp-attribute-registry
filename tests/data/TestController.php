<?php
//phpcs:ignoreFile

declare(strict_types=1);

namespace AttributeRegistry\Test\Data;

#[TestRoute('/users', 'GET')]
#[TestConfig(enabled: true, name: null, options: ['debug' => true, 'cache' => false])]
class TestController
{
    #[TestColumn('int', 11)]
    public int $id;

    #[TestColumn('string', 255)]
    public string $name;

    #[TestGet('/')]
    public function index(): void
    {
    }

    #[TestGet('/show')]
    public function show(#[TestParam(source: 'path', name: 'id')] int $id): void
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

#[TestWithEnum(label: 'Text Transformer', category: TestCategory::Text, priority: TestPriority::High)]
class TestTransformer
{
}
