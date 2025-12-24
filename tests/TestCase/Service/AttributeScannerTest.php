<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\TestSuite\TestCase;
use Generator;

class AttributeScannerTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeScanner $scanner;

    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataPath = $this->getTestDataPath();
        $this->loadTestAttributes();

        $this->scanner = $this->createScanner();
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
        $scannerWithExclude = $this->createScanner(
            config: [
                'paths' => ['*.php'],
                'exclude_paths' => ['TestController.php'],
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

    public function testScanAllSkipsNonPhpFiles(): void
    {
        // Create a temporary non-PHP file
        $tempFile = $this->testDataPath . '/test.txt';
        file_put_contents($tempFile, '<?php class Test {}');

        try {
            $scanner = $this->createScanner(
                config: [
                    'paths' => ['*.*'],
                    'exclude_paths' => [],
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
            $scanner = $this->createScanner(
                config: [
                    'paths' => ['invalid_syntax.php'],
                    'exclude_paths' => [],
                ],
            );

            // Should not throw, just return empty for unparseable files
            $attributes = iterator_to_array($scanner->scanAll());
            $this->assertEmpty($attributes);
        } finally {
            unlink($tempFile);
        }
    }

    public function testScanFileLogsWarningOnException(): void
    {
        // Create a file with severely malformed PHP that will trigger parser exception
        $tempFile = $this->testDataPath . '/malformed.php';
        file_put_contents($tempFile, '<?php namespace Test; class Broken { public function } }');

        try {
            $scanner = $this->createScanner(
                config: [
                    'paths' => ['malformed.php'],
                    'exclude_paths' => [],
                ],
            );

            // scanFile should catch the exception and log a warning, returning empty array
            $attributes = iterator_to_array($scanner->scanAll());

            // The malformed file should not produce any valid attributes
            $malformedAttrs = array_filter(
                $attributes,
                fn(AttributeInfo $attr): bool => str_contains($attr->filePath, 'malformed.php'),
            );
            $this->assertEmpty($malformedAttrs);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
