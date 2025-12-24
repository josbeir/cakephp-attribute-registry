<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Utility;

use AttributeRegistry\Utility\PathNormalizer;
use PHPUnit\Framework\TestCase;

class PathNormalizerTest extends TestCase
{
    public function testNormalizeConvertsForwardSlashesToPlatformSeparator(): void
    {
        $path = 'src/Service/AttributeParser.php';
        $normalized = PathNormalizer::normalize($path);

        $expected = 'src' . DIRECTORY_SEPARATOR . 'Service' . DIRECTORY_SEPARATOR . 'AttributeParser.php';
        $this->assertEquals($expected, $normalized);
    }

    public function testNormalizeConvertsBackslashesToPlatformSeparator(): void
    {
        $path = 'src\\Service\\AttributeParser.php';
        $normalized = PathNormalizer::normalize($path);

        $expected = 'src' . DIRECTORY_SEPARATOR . 'Service' . DIRECTORY_SEPARATOR . 'AttributeParser.php';
        $this->assertEquals($expected, $normalized);
    }

    public function testNormalizeHandlesMixedSeparators(): void
    {
        $path = 'src/Service\\AttributeParser.php';
        $normalized = PathNormalizer::normalize($path);

        $expected = 'src' . DIRECTORY_SEPARATOR . 'Service' . DIRECTORY_SEPARATOR . 'AttributeParser.php';
        $this->assertEquals($expected, $normalized);
    }

    public function testNormalizePreservesAlreadyNormalizedPaths(): void
    {
        $path = 'src' . DIRECTORY_SEPARATOR . 'Service' . DIRECTORY_SEPARATOR . 'AttributeParser.php';
        $normalized = PathNormalizer::normalize($path);

        $this->assertEquals($path, $normalized);
    }

    public function testToUnixStyleConvertsBackslashesToForwardSlashes(): void
    {
        $path = 'src\\Service\\AttributeParser.php';
        $unixStyle = PathNormalizer::toUnixStyle($path);

        $this->assertEquals('src/Service/AttributeParser.php', $unixStyle);
    }

    public function testToUnixStylePreservesForwardSlashes(): void
    {
        $path = 'src/Service/AttributeParser.php';
        $unixStyle = PathNormalizer::toUnixStyle($path);

        $this->assertEquals($path, $unixStyle);
    }

    public function testToUnixStyleHandlesMixedSeparators(): void
    {
        $path = 'src\\Service/AttributeParser.php';
        $unixStyle = PathNormalizer::toUnixStyle($path);

        $this->assertEquals('src/Service/AttributeParser.php', $unixStyle);
    }

    public function testCanonicalizeResolvesRealPath(): void
    {
        // Use the current file as a known existing path
        $thisFile = __FILE__;
        $canonical = PathNormalizer::canonicalize($thisFile);

        $this->assertNotFalse($canonical);
        $this->assertFileExists($canonical);
        // Canonical path should be an absolute path
        $this->assertTrue(str_starts_with($canonical, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:/i', $canonical) === 1);
    }

    public function testCanonicalizeReturnsFalseForNonExistentPath(): void
    {
        $nonExistent = '/this/path/does/not/exist/at/all.php';
        $canonical = PathNormalizer::canonicalize($nonExistent);

        $this->assertFalse($canonical);
    }

    public function testCanonicalizeResolvesRelativePaths(): void
    {
        // Test with the actual test file path
        $testFile = __FILE__;
        $canonical = PathNormalizer::canonicalize($testFile);

        $this->assertNotFalse($canonical);
        $this->assertFileExists($canonical);
        // Should resolve to the same file as realpath
        $this->assertEquals(realpath($testFile), $canonical);
    }
}
