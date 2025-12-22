<?php
//phpcs:ignoreFile

declare(strict_types=1);

namespace AttributeRegistry\Test\Data;

#[TestRoute('/users', 'GET')]
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
    public function show(int $id): void
    {
    }

    public function withoutAttribute(): void
    {
    }
}
