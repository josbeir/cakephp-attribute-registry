<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\ValueObject;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Test\Data\TestColumn;
use AttributeRegistry\Test\Data\TestRoute;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AttributeInfoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Load test attributes
        require_once dirname(__DIR__, 2) . '/data/TestAttributes.php';
    }

    public function testAttributeInfoCanBeCreated(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'MyClass',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\MyController',
            attributeName: 'App\Attribute\Route',
            arguments: ['path' => '/my-route', 'method' => 'GET'],
            filePath: '/app/src/Controller/MyController.php',
            lineNumber: 15,
            target: $target,
            fileModTime: 1640995200,
        );

        $this->assertEquals('App\Controller\MyController', $attributeInfo->className);
        $this->assertEquals('App\Attribute\Route', $attributeInfo->attributeName);
        $this->assertEquals(['path' => '/my-route', 'method' => 'GET'], $attributeInfo->arguments);
        $this->assertEquals('/app/src/Controller/MyController.php', $attributeInfo->filePath);
        $this->assertEquals(15, $attributeInfo->lineNumber);
        $this->assertEquals($target, $attributeInfo->target);
        $this->assertEquals(1640995200, $attributeInfo->fileModTime);
    }

    public function testAttributeInfoWithEmptyArguments(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::METHOD,
            'index',
            'MyController',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\MyController',
            attributeName: 'App\Attribute\Get',
            arguments: [],
            filePath: '/app/src/Controller/MyController.php',
            lineNumber: 25,
            target: $target,
            fileModTime: 1640995200,
        );

        $this->assertEmpty($attributeInfo->arguments);
        $this->assertEquals('index', $attributeInfo->target->targetName);
        $this->assertEquals('MyController', $attributeInfo->target->parentClass);
    }

    public function testAttributeInfoIsReadonly(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::PROPERTY,
            'myProperty',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Model\Entity\User',
            attributeName: 'App\Attribute\Column',
            arguments: ['type' => 'string'],
            filePath: '/app/src/Model/Entity/User.php',
            lineNumber: 10,
            target: $target,
            fileModTime: 1640995200,
        );

        // This should not be allowed in PHP 8.2+ readonly objects
        $this->expectException(Error::class);
        /** @phpstan-ignore property.readOnlyAssignOutOfClass */
        $attributeInfo->className = 'Different\Class';
    }

    public function testGetInstanceReturnsAttributeObject(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'TestController',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\TestController',
            attributeName: TestRoute::class,
            arguments: ['path' => '/users', 'method' => 'POST'],
            filePath: '/app/src/Controller/TestController.php',
            lineNumber: 10,
            target: $target,
            fileModTime: 1640995200,
        );

        $instance = $attributeInfo->getInstance();

        $this->assertInstanceOf(TestRoute::class, $instance);
        $this->assertEquals('/users', $instance->path);
        $this->assertEquals('POST', $instance->method);
    }

    public function testGetInstanceWithExpectedClass(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::PROPERTY,
            'name',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Entity\User',
            attributeName: TestColumn::class,
            arguments: ['type' => 'varchar', 'length' => 255],
            filePath: '/app/src/Entity/User.php',
            lineNumber: 15,
            target: $target,
            fileModTime: 1640995200,
        );

        $instance = $attributeInfo->getInstance(TestColumn::class);

        $this->assertInstanceOf(TestColumn::class, $instance);
        $this->assertEquals('varchar', $instance->type);
        $this->assertEquals(255, $instance->length);
    }

    public function testGetInstanceThrowsForNonExistentClass(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'SomeClass',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\SomeClass',
            attributeName: 'NonExistent\Attribute\Class',
            arguments: [],
            filePath: '/app/src/SomeClass.php',
            lineNumber: 5,
            target: $target,
            fileModTime: 1640995200,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Attribute class "NonExistent\Attribute\Class" does not exist');
        $attributeInfo->getInstance();
    }

    public function testGetInstanceThrowsForWrongExpectedClass(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'TestController',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\TestController',
            attributeName: TestRoute::class,
            arguments: ['path' => '/test'],
            filePath: '/app/src/Controller/TestController.php',
            lineNumber: 10,
            target: $target,
            fileModTime: 1640995200,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an instance of');
        $attributeInfo->getInstance(TestColumn::class);
    }

    public function testIsInstanceOf(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::CLASS_TYPE,
            'TestController',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\TestController',
            attributeName: TestRoute::class,
            arguments: ['path' => '/test'],
            filePath: '/app/src/Controller/TestController.php',
            lineNumber: 10,
            target: $target,
            fileModTime: 1640995200,
        );

        $this->assertTrue($attributeInfo->isInstanceOf(TestRoute::class));
        $this->assertFalse($attributeInfo->isInstanceOf(TestColumn::class));
    }
}
