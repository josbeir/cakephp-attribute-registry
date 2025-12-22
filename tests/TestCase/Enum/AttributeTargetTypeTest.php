<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Enum;

use AttributeRegistry\Enum\AttributeTargetType;
use PHPUnit\Framework\TestCase;
use ValueError;

class AttributeTargetTypeTest extends TestCase
{
    public function testAttributeTargetTypeHasExpectedValues(): void
    {
        $this->assertEquals('class', AttributeTargetType::CLASS_TYPE->value);
        $this->assertEquals('method', AttributeTargetType::METHOD->value);
        $this->assertEquals('property', AttributeTargetType::PROPERTY->value);
        $this->assertEquals('parameter', AttributeTargetType::PARAMETER->value);
        $this->assertEquals('constant', AttributeTargetType::CONSTANT->value);
    }

    public function testAttributeTargetTypeCanBeCreatedFromString(): void
    {
        $this->assertEquals(AttributeTargetType::CLASS_TYPE, AttributeTargetType::from('class'));
        $this->assertEquals(AttributeTargetType::METHOD, AttributeTargetType::from('method'));
        $this->assertEquals(AttributeTargetType::PROPERTY, AttributeTargetType::from('property'));
        $this->assertEquals(AttributeTargetType::PARAMETER, AttributeTargetType::from('parameter'));
        $this->assertEquals(AttributeTargetType::CONSTANT, AttributeTargetType::from('constant'));
    }

    public function testAttributeTargetTypeThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(ValueError::class);
        AttributeTargetType::from('invalid');
    }
}
