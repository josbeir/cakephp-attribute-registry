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
