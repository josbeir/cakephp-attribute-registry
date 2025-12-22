<?php
//phpcs:ignoreFile

declare(strict_types=1);

namespace AttributeRegistry\Test\Data;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TestRoute
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
    ) {
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class TestGet
{
    public function __construct(
        public ?string $path = null,
    ) {
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class TestColumn
{
    public function __construct(
        public string $type = 'string',
        public ?int $length = null,
    ) {
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class TestConfig
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public bool $enabled = true,
        public ?string $name = null,
        public array $options = [],
    ) {
    }
}

enum TestCategory: string
{
    case Text = 'text';
    case Number = 'number';
}

enum TestPriority
{
    case Low;
    case Medium;
    case High;
}

#[Attribute(Attribute::TARGET_CLASS)]
class TestWithEnum
{
    public function __construct(
        public string $label,
        public TestCategory $category,
        public TestPriority $priority = TestPriority::Medium,
    ) {
    }
}
