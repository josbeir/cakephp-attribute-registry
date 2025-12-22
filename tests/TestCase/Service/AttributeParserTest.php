<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Test\Data\TestColumn;
use AttributeRegistry\Test\Data\TestConst;
use AttributeRegistry\Test\Data\TestController;
use AttributeRegistry\Test\Data\TestGet;
use AttributeRegistry\Test\Data\TestParam;
use AttributeRegistry\Test\Data\TestRoute;
use AttributeRegistry\ValueObject\AttributeInfo;
use Exception;
use PHPUnit\Framework\TestCase;

class AttributeParserTest extends TestCase
{
    private AttributeParser $parser;

    private string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AttributeParser();
        $this->testFilePath = dirname(__DIR__, 2) . '/data/TestController.php';

        // Load the test attributes first
        require_once dirname(__DIR__, 2) . '/data/TestAttributes.php';
    }

    public function testAttributeParserCanBeCreated(): void
    {
        $parser = new AttributeParser();
        $this->assertInstanceOf(AttributeParser::class, $parser);
    }

    public function testParseFileReturnsAttributeInfoArray(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $this->assertNotEmpty($attributes);

        // Should find class, property, and method attributes
        $classAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::CLASS_TYPE);
        $propertyAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::PROPERTY);
        $methodAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::METHOD);

        $this->assertCount(3, $classAttributes); // TestRoute, TestConfig on TestController + TestWithEnum on TestTransformer
        $this->assertCount(2, $propertyAttributes); // TestColumn on id and name
        $this->assertCount(3, $methodAttributes); // TestGet on index and show methods, TestConfig on disabled
    }

    public function testParseFileExtractsClassAttributes(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $classAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::CLASS_TYPE);
        $this->assertNotEmpty($classAttributes);
        $classAttribute = reset($classAttributes);
        $this->assertInstanceOf(AttributeInfo::class, $classAttribute);

        $this->assertEquals(TestController::class, $classAttribute->className);
        $this->assertEquals(TestRoute::class, $classAttribute->attributeName);
        $this->assertEquals(['path' => '/users', 'method' => 'GET'], $classAttribute->arguments);
        $this->assertEquals('TestController', $classAttribute->target->targetName);
        $this->assertEquals($this->testFilePath, $classAttribute->filePath);
    }

    public function testParseFileExtractsPropertyAttributes(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $propertyAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::PROPERTY);

        $idAttribute = null;
        $nameAttribute = null;

        foreach ($propertyAttributes as $attr) {
            if ($attr->target->targetName === 'id') {
                $idAttribute = $attr;
            } elseif ($attr->target->targetName === 'name') {
                $nameAttribute = $attr;
            }
        }

        $this->assertNotNull($idAttribute);
        $this->assertEquals(TestColumn::class, $idAttribute->attributeName);
        $this->assertEquals(['type' => 'int', 'length' => 11], $idAttribute->arguments);
        $this->assertEquals('TestController', $idAttribute->target->parentClass);

        $this->assertNotNull($nameAttribute);
        $this->assertEquals(TestColumn::class, $nameAttribute->attributeName);
        $this->assertEquals(['type' => 'string', 'length' => 255], $nameAttribute->arguments);
    }

    public function testParseFileExtractsMethodAttributes(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $methodAttributes = array_filter($attributes, fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::METHOD);

        $indexAttribute = null;
        $showAttribute = null;

        foreach ($methodAttributes as $attr) {
            if ($attr->target->targetName === 'index') {
                $indexAttribute = $attr;
            } elseif ($attr->target->targetName === 'show') {
                $showAttribute = $attr;
            }
        }

        $this->assertNotNull($indexAttribute);
        $this->assertEquals(TestGet::class, $indexAttribute->attributeName);
        $this->assertEquals(['path' => '/'], $indexAttribute->arguments);
        $this->assertEquals('TestController', $indexAttribute->target->parentClass);

        $this->assertNotNull($showAttribute);
        $this->assertEquals(['path' => '/show'], $showAttribute->arguments);
    }

    public function testParseNonexistentFileThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->parser->parseFile('/nonexistent/file.php');
    }

    public function testParseFileWithoutClassReturnsEmpty(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'attr_test');
        file_put_contents($tempFile, "<?php\n// No classes here");

        $attributes = $this->parser->parseFile($tempFile);

        $this->assertEmpty($attributes);

        unlink($tempFile);
    }

    public function testExcludeAttributesByExactFqcn(): void
    {
        $parser = new AttributeParser([TestRoute::class]);
        $attributes = $parser->parseFile($this->testFilePath);

        // TestRoute should be excluded
        $routeAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->attributeName === TestRoute::class,
        );
        $this->assertEmpty($routeAttributes);

        // Other attributes should still be present
        $columnAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->attributeName === TestColumn::class,
        );
        $this->assertNotEmpty($columnAttributes);
    }

    public function testExcludeMultipleAttributes(): void
    {
        $parser = new AttributeParser([TestRoute::class, TestColumn::class]);
        $attributes = $parser->parseFile($this->testFilePath);

        // Both should be excluded
        $routeAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->attributeName === TestRoute::class,
        );
        $columnAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->attributeName === TestColumn::class,
        );

        $this->assertEmpty($routeAttributes);
        $this->assertEmpty($columnAttributes);

        // TestGet should still be present
        $getAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->attributeName === TestGet::class,
        );
        $this->assertNotEmpty($getAttributes);
    }

    public function testExcludeAttributesByNamespaceWildcard(): void
    {
        // Exclude all attributes in AttributeRegistry\Test\Data namespace
        $parser = new AttributeParser(['AttributeRegistry\Test\Data\*']);
        $attributes = $parser->parseFile($this->testFilePath);

        // All test attributes should be excluded
        $this->assertEmpty($attributes);
    }

    public function testEmptyExcludeListIncludesAllAttributes(): void
    {
        $parser = new AttributeParser([]);
        $attributes = $parser->parseFile($this->testFilePath);

        // Should have all attributes
        $this->assertNotEmpty($attributes);
        $this->assertGreaterThanOrEqual(8, count($attributes));
    }

    public function testExcludeNonExistentAttributeDoesNotError(): void
    {
        $parser = new AttributeParser(['NonExistent\Attribute\Class']);
        $attributes = $parser->parseFile($this->testFilePath);

        // Should still find all attributes
        $this->assertNotEmpty($attributes);
    }

    public function testParseFileExtractsParameterAttributes(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $parameterAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::PARAMETER,
        );

        $this->assertCount(1, $parameterAttributes);

        $paramAttr = reset($parameterAttributes);
        $this->assertInstanceOf(AttributeInfo::class, $paramAttr);
        $this->assertEquals(TestParam::class, $paramAttr->attributeName);
        $this->assertEquals(TestController::class, $paramAttr->className);
        $this->assertEquals(['source' => 'path', 'name' => 'id'], $paramAttr->arguments);
        $this->assertEquals('id', $paramAttr->target->targetName);
        $this->assertEquals('show', $paramAttr->target->parentClass);
    }

    public function testParseFileExtractsConstantAttributes(): void
    {
        $attributes = $this->parser->parseFile($this->testFilePath);

        $constantAttributes = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::CONSTANT,
        );

        $this->assertCount(1, $constantAttributes);

        $constAttr = reset($constantAttributes);
        $this->assertInstanceOf(AttributeInfo::class, $constAttr);
        $this->assertEquals(TestConst::class, $constAttr->attributeName);
        $this->assertEquals(TestController::class, $constAttr->className);
        $this->assertEquals(['description' => 'Active status', 'deprecated' => false], $constAttr->arguments);
        $this->assertEquals('STATUS_ACTIVE', $constAttr->target->targetName);
        $this->assertEquals('TestController', $constAttr->target->parentClass);
    }
}
