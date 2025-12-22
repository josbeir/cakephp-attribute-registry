<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\ValueObject;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeTarget;
use Error;
use PHPUnit\Framework\TestCase;

class AttributeTargetTest extends TestCase
{
    public function testAttributeTargetCanBeCreated(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'MyClass',
        );

        $this->assertEquals(AttributeTargetType::CLASS_TYPE, $target->type);
        $this->assertEquals('MyClass', $target->targetName);
        $this->assertNull($target->parentClass);
    }

    public function testAttributeTargetCanBeCreatedWithParentClass(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::METHOD,
            'myMethod',
            'MyClass',
        );

        $this->assertEquals(AttributeTargetType::METHOD, $target->type);
        $this->assertEquals('myMethod', $target->targetName);
        $this->assertEquals('MyClass', $target->parentClass);
    }

    public function testAttributeTargetIsReadonly(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::PROPERTY,
            'myProperty',
        );

        // These should not be allowed in PHP 8.2+ readonly objects
        $this->expectException(Error::class);
        $target->type = AttributeTargetType::METHOD;
    }
}
