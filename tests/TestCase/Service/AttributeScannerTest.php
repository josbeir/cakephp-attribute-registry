<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\TestSuite\TestCase;
use Generator;

class AttributeScannerTest extends TestCase
{
    private AttributeScanner $scanner;

    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataPath = dirname(__DIR__, 2) . '/data';

        // Load test attributes
        require_once $this->testDataPath . '/TestAttributes.php';

        $pathResolver = new PathResolver($this->testDataPath);
        $parser = new AttributeParser();

        $this->scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 1024 * 1024,
            ],
        );
    }

    public function testAttributeScannerCanBeCreated(): void
    {
        $this->assertInstanceOf(AttributeScanner::class, $this->scanner);
    }

    public function testScanAllReturnsGenerator(): void
    {
        $result = $this->scanner->scanAll();

        $this->assertInstanceOf(Generator::class, $result);
    }

    public function testScanAllFindsAttributes(): void
    {
        $attributes = iterator_to_array($this->scanner->scanAll());

        $this->assertNotEmpty($attributes);

        // Should find class attribute (TestRoute on TestController)
        $classAttrs = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::CLASS_TYPE,
        );
        $this->assertNotEmpty($classAttrs);

        // Should find method attributes (TestGet on index and show)
        $methodAttrs = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::METHOD,
        );
        $this->assertNotEmpty($methodAttrs);

        // Should find property attributes (TestColumn on id and name)
        $propertyAttrs = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => $attr->target->type === AttributeTargetType::PROPERTY,
        );
        $this->assertNotEmpty($propertyAttrs);
    }

    public function testScanAllCachesResults(): void
    {
        // First scan
        $attributes1 = iterator_to_array($this->scanner->scanAll());

        // Second scan should return same results
        $attributes2 = iterator_to_array($this->scanner->scanAll());

        $this->assertCount(count($attributes1), $attributes2);
    }

    public function testScanAllRespectsExcludePaths(): void
    {
        $pathResolver = new PathResolver($this->testDataPath);
        $parser = new AttributeParser();

        $scannerWithExclude = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => ['TestController.php'],
                'max_file_size' => 1024 * 1024,
            ],
        );

        $attributes = iterator_to_array($scannerWithExclude->scanAll());

        // Should not find TestController attributes
        $testControllerAttrs = array_filter(
            $attributes,
            fn(AttributeInfo $attr): bool => str_contains($attr->className, 'TestController'),
        );
        $this->assertEmpty($testControllerAttrs);
    }

    public function testScanAllRespectsMaxFileSize(): void
    {
        $pathResolver = new PathResolver($this->testDataPath);
        $parser = new AttributeParser();

        // Set very small max file size
        $scannerWithLimit = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 10, // 10 bytes - should exclude all files
            ],
        );

        $attributes = iterator_to_array($scannerWithLimit->scanAll());

        $this->assertEmpty($attributes);
    }

    public function testScanAllSkipsNonPhpFiles(): void
    {
        // Create a temporary non-PHP file
        $tempFile = $this->testDataPath . '/test.txt';
        file_put_contents($tempFile, '<?php class Test {}');

        try {
            $pathResolver = new PathResolver($this->testDataPath);
            $parser = new AttributeParser();

            $scanner = new AttributeScanner(
                $parser,
                $pathResolver,
                [
                    'paths' => ['*.*'],
                    'exclude_paths' => [],
                    'max_file_size' => 1024 * 1024,
                ],
            );

            $attributes = iterator_to_array($scanner->scanAll());

            // Should only have attributes from PHP files
            foreach ($attributes as $attr) {
                $this->assertStringEndsWith('.php', $attr->filePath);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function testScanAllHandlesParseErrors(): void
    {
        // Create a temporary PHP file with invalid syntax
        $tempFile = $this->testDataPath . '/invalid_syntax.php';
        file_put_contents($tempFile, '<?php class { invalid }');

        try {
            $pathResolver = new PathResolver($this->testDataPath);
            $parser = new AttributeParser();

            $scanner = new AttributeScanner(
                $parser,
                $pathResolver,
                [
                    'paths' => ['invalid_syntax.php'],
                    'exclude_paths' => [],
                    'max_file_size' => 1024 * 1024,
                ],
            );

            // Should not throw, just return empty for unparseable files
            $attributes = iterator_to_array($scanner->scanAll());
            $this->assertEmpty($attributes);
        } finally {
            unlink($tempFile);
        }
    }
}
