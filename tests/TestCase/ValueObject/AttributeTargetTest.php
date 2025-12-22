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
        /** @phpstan-ignore property.readOnlyAssignOutOfClass */
        $target->type = AttributeTargetType::METHOD;
    }

    public function testToArray(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::METHOD,
            'index',
            'UserController',
        );

        $array = $target->toArray();

        $this->assertEquals('method', $array['type']);
        $this->assertEquals('index', $array['targetName']);
        $this->assertEquals('UserController', $array['parentClass']);
    }

    public function testToArrayWithNullParentClass(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'MyClass',
        );

        $array = $target->toArray();

        $this->assertEquals('class', $array['type']);
        $this->assertEquals('MyClass', $array['targetName']);
        $this->assertNull($array['parentClass']);
    }

    public function testFromArray(): void
    {
        $data = [
            'type' => 'property',
            'targetName' => 'email',
            'parentClass' => 'User',
        ];

        $target = AttributeTarget::fromArray($data);

        $this->assertEquals(AttributeTargetType::PROPERTY, $target->type);
        $this->assertEquals('email', $target->targetName);
        $this->assertEquals('User', $target->parentClass);
    }

    public function testFromArrayWithNullParentClass(): void
    {
        $data = [
            'type' => 'class',
            'targetName' => 'MyController',
            'parentClass' => null,
        ];

        $target = AttributeTarget::fromArray($data);

        $this->assertEquals(AttributeTargetType::CLASS_TYPE, $target->type);
        $this->assertEquals('MyController', $target->targetName);
        $this->assertNull($target->parentClass);
    }

    public function testFromArrayWithMissingParentClass(): void
    {
        $data = [
            'type' => 'constant',
            'targetName' => 'STATUS_ACTIVE',
        ];

        $target = AttributeTarget::fromArray($data);

        $this->assertEquals(AttributeTargetType::CONSTANT, $target->type);
        $this->assertEquals('STATUS_ACTIVE', $target->targetName);
        $this->assertNull($target->parentClass);
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $original = new AttributeTarget(
            AttributeTargetType::PARAMETER,
            'userId',
            'findUser',
        );

        $array = $original->toArray();
        $restored = AttributeTarget::fromArray($array);

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->targetName, $restored->targetName);
        $this->assertEquals($original->parentClass, $restored->parentClass);
    }

    public function testAllTargetTypes(): void
    {
        $types = [
            AttributeTargetType::CLASS_TYPE,
            AttributeTargetType::METHOD,
            AttributeTargetType::PROPERTY,
            AttributeTargetType::PARAMETER,
            AttributeTargetType::CONSTANT,
        ];

        foreach ($types as $type) {
            $target = new AttributeTarget($type, 'test');
            $this->assertEquals($type, $target->type);

            $array = $target->toArray();
            $restored = AttributeTarget::fromArray($array);
            $this->assertEquals($type, $restored->type);
        }
    }
}
