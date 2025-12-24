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
            fileHash: '',
        );

        $this->assertEquals('App\Controller\MyController', $attributeInfo->className);
        $this->assertEquals('App\Attribute\Route', $attributeInfo->attributeName);
        $this->assertEquals(['path' => '/my-route', 'method' => 'GET'], $attributeInfo->arguments);
        $this->assertEquals('/app/src/Controller/MyController.php', $attributeInfo->filePath);
        $this->assertEquals(15, $attributeInfo->lineNumber);
        $this->assertEquals($target, $attributeInfo->target);
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
            fileHash: '',
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
            fileHash: '',
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
            fileHash: '',
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
            fileHash: '',
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
            fileHash: '',
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
            fileHash: '',
        );

        $this->assertTrue($attributeInfo->isInstanceOf(TestRoute::class));
        $this->assertFalse($attributeInfo->isInstanceOf(TestColumn::class));
    }

    public function testToArray(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::METHOD,
            'index',
            'MyController',
        );

        $attributeInfo = new AttributeInfo(
            className: 'App\Controller\MyController',
            attributeName: 'App\Attribute\Route',
            arguments: ['path' => '/users'],
            filePath: '/app/src/Controller/MyController.php',
            lineNumber: 20,
            target: $target,
            fileHash: '',
        );

        $array = $attributeInfo->toArray();

        $this->assertEquals('App\Controller\MyController', $array['className']);
        $this->assertEquals('App\Attribute\Route', $array['attributeName']);
        $this->assertEquals(['path' => '/users'], $array['arguments']);
        $this->assertEquals('/app/src/Controller/MyController.php', $array['filePath']);
        $this->assertEquals(20, $array['lineNumber']);
        $this->assertIsArray($array['target']);
        $this->assertEquals('method', $array['target']['type']);
        $this->assertEquals('index', $array['target']['targetName']);
        $this->assertEquals('MyController', $array['target']['parentClass']);
    }

    public function testFromArray(): void
    {
        $data = [
            'className' => 'App\Controller\UserController',
            'attributeName' => 'App\Attribute\Get',
            'arguments' => ['path' => '/users/{id}'],
            'filePath' => '/app/src/Controller/UserController.php',
            'lineNumber' => 30,
            'target' => [
                'type' => 'method',
                'targetName' => 'view',
                'parentClass' => 'UserController',
            ],
        ];

        $attributeInfo = AttributeInfo::fromArray($data);

        $this->assertEquals('App\Controller\UserController', $attributeInfo->className);
        $this->assertEquals('App\Attribute\Get', $attributeInfo->attributeName);
        $this->assertEquals(['path' => '/users/{id}'], $attributeInfo->arguments);
        $this->assertEquals('/app/src/Controller/UserController.php', $attributeInfo->filePath);
        $this->assertEquals(30, $attributeInfo->lineNumber);
        $this->assertEquals(AttributeTargetType::METHOD, $attributeInfo->target->type);
        $this->assertEquals('view', $attributeInfo->target->targetName);
        $this->assertEquals('UserController', $attributeInfo->target->parentClass);
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::PROPERTY,
            'email',
            'User',
        );

        $original = new AttributeInfo(
            className: 'App\Entity\User',
            attributeName: TestColumn::class,
            arguments: ['type' => 'varchar', 'length' => 100],
            filePath: '/app/src/Entity/User.php',
            lineNumber: 25,
            target: $target,
            fileHash: '',
        );

        $array = $original->toArray();
        $restored = AttributeInfo::fromArray($array);

        $this->assertEquals($original->className, $restored->className);
        $this->assertEquals($original->attributeName, $restored->attributeName);
        $this->assertEquals($original->arguments, $restored->arguments);
        $this->assertEquals($original->filePath, $restored->filePath);
        $this->assertEquals($original->lineNumber, $restored->lineNumber);
        $this->assertEquals($original->target->type, $restored->target->type);
        $this->assertEquals($original->target->targetName, $restored->target->targetName);
        $this->assertEquals($original->target->parentClass, $restored->target->parentClass);
    }

    public function testSetStateRestoresObjectState(): void
    {
        $target = new AttributeTarget(
            AttributeTargetType::METHOD,
            'index',
            'TestController',
        );

        $original = new AttributeInfo(
            className: 'App\Controller\TestController',
            attributeName: TestRoute::class,
            arguments: ['path' => '/test', 'method' => 'GET'],
            filePath: '/app/src/Controller/TestController.php',
            lineNumber: 15,
            target: $target,
            fileHash: 'abc123',
        );

        // Test __set_state method directly with array data
        $data = $original->toArray();
        $restored = AttributeInfo::__set_state($data);

        $this->assertInstanceOf(AttributeInfo::class, $restored);
        $this->assertEquals($original->className, $restored->className);
        $this->assertEquals($original->attributeName, $restored->attributeName);
        $this->assertEquals($original->arguments, $restored->arguments);
        $this->assertEquals($original->filePath, $restored->filePath);
        $this->assertEquals($original->lineNumber, $restored->lineNumber);
        $this->assertEquals($original->target->type, $restored->target->type);
        $this->assertEquals($original->fileHash, $restored->fileHash);
    }
}
