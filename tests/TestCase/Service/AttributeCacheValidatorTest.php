<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Service\AttributeCacheValidator;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use AttributeRegistry\Utility\HashUtility;
use AttributeRegistry\ValueObject\AttributeCacheValidationResult;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use ReflectionClass;

class AttributeCacheValidatorTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private string $tempPath;

    private AttributeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/validator_test_' . uniqid() . '/';
        mkdir($this->tempPath, 0755, true);

        Cache::setConfig('validator_test', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $this->registry = $this->createRegistry($this->tempPath, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Cache::clear('validator_test');
        Cache::drop('validator_test');
        $this->removeDirectory($this->tempPath);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . $file;
            is_dir($path) ? $this->removeDirectory($path . '/') : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * RED TEST: Validator returns result object
     */
    public function testValidateReturnsResultObject(): void
    {
        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        $this->assertInstanceOf(AttributeCacheValidationResult::class, $result);
    }

    /**
     * RED TEST: Validator detects missing file
     */
    public function testValidateDetectsMissingFile(): void
    {
        $validator = new AttributeCacheValidator($this->registry);

        // Create a fake attribute with non-existent file
        $attr = $this->createTestAttribute('/not/a/real/file.php', 'deadbeef');

        // Manually populate registry cache with invalid data
        $this->registry->clearCache();

        // We need to somehow inject this bad data - let's use reflection
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr]);

        $result = $validator->validate();

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower($result->errors[0]));
    }

    /**
     * RED TEST: Validator detects hash mismatch
     */
    public function testValidateDetectsHashMismatch(): void
    {
        $tmpFile = $this->tempPath . 'test.php';
        file_put_contents($tmpFile, '<?php // test');

        $wrongHash = 'deadbeef';
        $attr = $this->createTestAttribute($tmpFile, $wrongHash);

        $this->registry->clearCache();
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr]);

        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('hash', strtolower($result->errors[0]));
    }

    /**
     * RED TEST: Validator passes for valid file and hash
     */
    public function testValidatePassesForValidFileAndHash(): void
    {
        $tmpFile = $this->tempPath . 'test.php';
        file_put_contents($tmpFile, '<?php // test');
        $hash = HashUtility::hashFile($tmpFile);
        assert(is_string($hash));

        $attr = $this->createTestAttribute($tmpFile, $hash);

        $this->registry->clearCache();
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr]);

        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertEquals(1, $result->totalAttributes);
        $this->assertEquals(1, $result->totalFiles);
    }

    /**
     * RED TEST: Validator handles empty cache
     */
    public function testValidateHandlesEmptyCache(): void
    {
        $this->registry->clearCache();

        // Inject empty discovered attributes
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, []);

        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertEquals(0, $result->totalAttributes);
    }

    /**
     * RED TEST: Validator counts unique files correctly
     */
    public function testValidateCountsUniqueFiles(): void
    {
        $tmpFile = $this->tempPath . 'test.php';
        file_put_contents($tmpFile, '<?php // test');
        $hash = HashUtility::hashFile($tmpFile);
        assert(is_string($hash));

        // Two attributes from same file
        $attr1 = $this->createTestAttribute($tmpFile, $hash);
        $attr2 = $this->createTestAttribute($tmpFile, $hash);

        $this->registry->clearCache();
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr1, $attr2]);

        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        $this->assertTrue($result->valid);
        $this->assertEquals(2, $result->totalAttributes);
        $this->assertEquals(1, $result->totalFiles); // Only 1 unique file
    }

    /**
     * RED TEST: Validator skips attributes without hash (backward compat)
     */
    public function testValidateSkipsAttributesWithoutHash(): void
    {
        $tmpFile = $this->tempPath . 'test.php';
        file_put_contents($tmpFile, '<?php // test');

        // Attribute without hash (empty string)
        $attr = $this->createTestAttribute($tmpFile, '');

        $this->registry->clearCache();
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr]);

        $validator = new AttributeCacheValidator($this->registry);
        $result = $validator->validate();

        // Should pass validation (hash not checked)
        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
    }

    /**
     * Test helper methods on validation result
     */
    public function testValidationResultHelperMethods(): void
    {
        // Test success result
        $successResult = AttributeCacheValidationResult::success(10, 5);
        $this->assertTrue($successResult->valid);
        $this->assertFalse($successResult->hasErrors());
        $this->assertFalse($successResult->hasWarnings());
        $this->assertEquals(10, $successResult->totalAttributes);
        $this->assertEquals(5, $successResult->totalFiles);

        // Test failure result with errors
        $failureResult = AttributeCacheValidationResult::failure(['Error 1', 'Error 2'], 10, 5);
        $this->assertFalse($failureResult->valid);
        $this->assertTrue($failureResult->hasErrors());
        $this->assertFalse($failureResult->hasWarnings());
        $this->assertCount(2, $failureResult->errors);

        // Test notCached result
        $notCachedResult = AttributeCacheValidationResult::notCached();
        $this->assertFalse($notCachedResult->valid);
        $this->assertTrue($notCachedResult->hasErrors());
        $this->assertFalse($notCachedResult->hasWarnings());
        $this->assertEquals(0, $notCachedResult->totalAttributes);
        $this->assertEquals(0, $notCachedResult->totalFiles);
        $this->assertStringContainsString('Cache not found', $notCachedResult->errors[0]);
    }
}
