<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\CompiledCache;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use AttributeRegistry\Utility\HashUtility;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Cake\TestSuite\TestCase;
use Cake\Utility\Filesystem;
use stdClass;

class CompiledCacheTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private CompiledCache $cache;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/attribute_registry_compiled_test_' . uniqid() . '/';
        mkdir($this->tempPath, 0755, true);

        $this->cache = new CompiledCache($this->tempPath, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempPath)) {
            (new Filesystem())->deleteDir($this->tempPath);
        }
    }

    public function testCompiledCacheCanBeCreated(): void
    {
        $cache = new CompiledCache($this->tempPath, true);
        $this->assertInstanceOf(CompiledCache::class, $cache);
    }

    public function testCacheCanBeDisabled(): void
    {
        $cache = new CompiledCache($this->tempPath, false);
        $this->assertFalse($cache->isEnabled());
    }

    public function testCacheIsEnabledByDefault(): void
    {
        $this->assertTrue($this->cache->isEnabled());
    }

    public function testGetReturnsNullForNonexistentKey(): void
    {
        $result = $this->cache->get('nonexistent_key');
        $this->assertNull($result);
    }

    public function testGetReturnsNullWhenDisabled(): void
    {
        $cache = new CompiledCache($this->tempPath, false);
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);

        $cache->set('test_key', [$attr]);
        $result = $cache->get('test_key');

        $this->assertNull($result);
    }

    public function testSetReturnsFalseWhenDisabled(): void
    {
        $cache = new CompiledCache($this->tempPath, false);
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);

        $result = $cache->set('test_key', [$attr]);

        $this->assertFalse($result);
    }

    public function testSetAndGetSimpleAttribute(): void
    {
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);

        $setResult = $this->cache->set('test_key', [$attr]);
        $this->assertTrue($setResult);

        $getResult = $this->cache->get('test_key');
        $this->assertIsArray($getResult);
        $this->assertCount(1, $getResult);
        $this->assertInstanceOf(AttributeInfo::class, $getResult[0]);

        // Verify all properties match
        $this->assertEquals($attr->className, $getResult[0]->className);
        $this->assertEquals($attr->attributeName, $getResult[0]->attributeName);
        $this->assertEquals($attr->arguments, $getResult[0]->arguments);
        $this->assertEquals($attr->filePath, $getResult[0]->filePath);
        $this->assertEquals($attr->lineNumber, $getResult[0]->lineNumber);
        $this->assertEquals($attr->target->type, $getResult[0]->target->type);
        $this->assertEquals($attr->target->targetName, $getResult[0]->target->targetName);
        $this->assertEquals($attr->target->parentClass, $getResult[0]->target->parentClass);
    }

    public function testSetAndGetMultipleAttributes(): void
    {
        $attr1 = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $attr2 = new AttributeInfo(
            className: 'App\\Controller\\PostsController',
            attributeName: 'App\\Cache',
            arguments: ['ttl' => 300],
            filePath: '/app/src/Controller/PostsController.php',
            lineNumber: 42,
            target: new AttributeTarget(
                type: AttributeTargetType::METHOD,
                targetName: 'index',
                parentClass: 'PostsController',
            ),
            fileHash: '',
        );

        $this->cache->set('test_key', [$attr1, $attr2]);
        $result = $this->cache->get('test_key');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($attr1->className, $result[0]->className);
        $this->assertEquals($attr2->className, $result[1]->className);
    }

    public function testHandlesComplexArguments(): void
    {
        $attr = new AttributeInfo(
            className: 'App\\Controller\\UsersController',
            attributeName: 'App\\Route',
            arguments: [
                'path' => '/users/{id}',
                'methods' => ['GET', 'POST'],
                'options' => [
                    'auth' => true,
                    'roles' => ['admin', 'user'],
                ],
                'null_value' => null,
                'bool_value' => false,
                'int_value' => 42,
                'float_value' => 3.14,
            ],
            filePath: '/app/src/Controller.php',
            lineNumber: 42,
            target: new AttributeTarget(
                type: AttributeTargetType::METHOD,
                targetName: 'index',
                parentClass: 'UsersController',
            ),
            fileHash: '',
        );

        $this->cache->set('complex', [$attr]);
        $loaded = $this->cache->get('complex');

        $this->assertIsArray($loaded);
        $this->assertEquals($attr->arguments, $loaded[0]->arguments);
    }

    public function testHandlesSpecialCharactersInStrings(): void
    {
        $attr = new AttributeInfo(
            className: 'App\\Controller\\Test\'Quote',
            attributeName: 'App\\Route',
            arguments: [
                'path' => '/test"quotes\'mixed',
                'desc' => "Multi\nLine\tString",
            ],
            filePath: "/app/src/Test'File.php",
            lineNumber: 10,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: "Test'Class",
            ),
            fileHash: '',
        );

        $this->cache->set('special', [$attr]);
        $loaded = $this->cache->get('special');

        $this->assertIsArray($loaded);
        $this->assertEquals($attr->className, $loaded[0]->className);
        $this->assertEquals($attr->arguments, $loaded[0]->arguments);
        $this->assertEquals($attr->filePath, $loaded[0]->filePath);
    }

    public function testHandlesBackslashesInNamespaces(): void
    {
        $attr = new AttributeInfo(
            className: 'Deep\\Nested\\Namespace\\Controller',
            attributeName: 'Deep\\Nested\\Attribute',
            arguments: [],
            filePath: '/app/src/Deep/Nested/Controller.php',
            lineNumber: 5,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'Controller',
            ),
            fileHash: '',
        );

        $this->cache->set('namespaces', [$attr]);
        $loaded = $this->cache->get('namespaces');

        $this->assertIsArray($loaded);
        $this->assertEquals($attr->className, $loaded[0]->className);
        $this->assertEquals($attr->attributeName, $loaded[0]->attributeName);
    }

    public function testDeleteRemovesCacheFile(): void
    {
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $this->cache->set('test_key', [$attr]);

        $result = $this->cache->delete('test_key');
        $this->assertTrue($result);

        $getResult = $this->cache->get('test_key');
        $this->assertNull($getResult);
    }

    public function testDeleteReturnsTrueForNonexistentKey(): void
    {
        $result = $this->cache->delete('nonexistent');
        $this->assertTrue($result); // Nothing to delete is success
    }

    public function testClearRemovesAllCacheFiles(): void
    {
        $attr1 = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $this->cache->set('key1', [$attr1]);
        $this->cache->set('key2', [$attr1]);
        $this->cache->set('key3', [$attr1]);

        $result = $this->cache->clear();
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testGeneratesValidPHPSyntax(): void
    {
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $this->cache->set('test', [$attr]);

        $filePath = $this->tempPath . 'test.php';
        $this->assertFileExists($filePath);

        // Read the file and check for valid PHP
        $content = file_get_contents($filePath);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);
        $this->assertStringContainsString('return [', $content);
        $this->assertStringContainsString('new \\AttributeRegistry\\ValueObject\\AttributeInfo', $content);
    }

    public function testCacheFileContainsMetadata(): void
    {
        $attr = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $this->cache->set('test', [$attr]);

        $filePath = $this->tempPath . 'test.php';
        $content = file_get_contents($filePath);
        $this->assertNotFalse($content);

        $this->assertStringContainsString('Pre-compiled Attribute Registry Cache', $content);
        $this->assertStringContainsString('Generated:', $content);
        $this->assertStringContainsString('DO NOT EDIT THIS FILE MANUALLY', $content);
    }

    public function testHandlesEmptyArray(): void
    {
        $result = $this->cache->set('empty', []);
        $this->assertTrue($result);

        $loaded = $this->cache->get('empty');
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    public function testOverwritesExistingCache(): void
    {
        $attr1 = $this->createTestAttribute('/app/src/Controller/UsersController.php', '', 'App\\Controller\\UsersController', 'App\\Route', ['path' => '/users'], 15);
        $attr2 = new AttributeInfo(
            className: 'Different\\Class',
            attributeName: 'Different\\Attr',
            arguments: [],
            filePath: '/different.php',
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'Different',
            ),
            fileHash: '',
        );

        $this->cache->set('test', [$attr1]);
        $this->cache->set('test', [$attr2]);

        $result = $this->cache->get('test');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Different\\Class', $result[0]->className);
    }

    /**
     * Test that Closures in attribute arguments are rejected
     */
    public function testSetRejectsClosuresInArguments(): void
    {
        $target = new AttributeTarget(AttributeTargetType::CLASS_TYPE, 'Test\\MyClass');
        $attr = new AttributeInfo(
            className: 'Test\\MyClass',
            attributeName: 'Test\\MyAttribute',
            arguments: ['callback' => fn(): string => 'test'],
            filePath: '/test/file.php',
            lineNumber: 10,
            target: $target,
            fileHash: '',
        );

        $result = $this->cache->set('test', [$attr]);
        $this->assertFalse($result, 'set() should return false when attributes contain closures');
    }

    /**
     * Test that resources in attribute arguments are rejected
     */
    public function testSetRejectsResourcesInArguments(): void
    {
        $resource = fopen('php://memory', 'r');
        try {
            $target = new AttributeTarget(AttributeTargetType::CLASS_TYPE, 'Test\\MyClass');
            $attr = new AttributeInfo(
                className: 'Test\\MyClass',
                attributeName: 'Test\\MyAttribute',
                arguments: ['handle' => $resource],
                filePath: '/test/file.php',
                lineNumber: 10,
                target: $target,
                fileHash: '',
            );

            $result = $this->cache->set('test', [$attr]);
            $this->assertFalse($result, 'set() should return false when attributes contain resources');
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * Test that objects without __set_state are rejected
     */
    public function testSetRejectsObjectsWithoutSetState(): void
    {
        $target = new AttributeTarget(AttributeTargetType::CLASS_TYPE, 'Test\\MyClass');
        $attr = new AttributeInfo(
            className: 'Test\\MyClass',
            attributeName: 'Test\\MyAttribute',
            arguments: ['obj' => new stdClass()],
            filePath: '/test/file.php',
            lineNumber: 10,
            target: $target,
        );

        $result = $this->cache->set('test', [$attr]);
        $this->assertFalse($result, 'set() should return false when attributes contain objects without __set_state');
    }

    /**
     * RED TEST: Test that fileHash is stored when provided
     */
    public function testFileHashIsStoredInCache(): void
    {
        $testFile = __FILE__;
        $fileHash = HashUtility::hashFile($testFile);
        $this->assertNotFalse($fileHash);

        $attr = new AttributeInfo(
            className: 'App\\Controller\\TestController',
            attributeName: 'App\\Route',
            arguments: ['path' => '/test'],
            filePath: $testFile,
            lineNumber: 10,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestController',
            ),
            fileHash: $fileHash,
        );

        $this->cache->set('test', [$attr]);
        $loaded = $this->cache->get('test');

        $this->assertIsArray($loaded);
        $this->assertNotEmpty($loaded[0]->fileHash);
        $this->assertEquals($attr->fileHash, $loaded[0]->fileHash);
    }

    /**
     * RED TEST: Test that cache validation can be enabled
     */
    public function testCacheValidationCanBeConfigured(): void
    {
        $cache = new CompiledCache($this->tempPath, true, true);
        $this->assertTrue($cache->isValidationEnabled());

        $cache = new CompiledCache($this->tempPath, true, false);
        $this->assertFalse($cache->isValidationEnabled());
    }

    /**
     * RED TEST: Test that stale entries are filtered when validation is enabled
     */
    public function testStaleEntriesAreFilteredWithValidation(): void
    {
        // Create a temporary file to test with
        $testFile = $this->tempPath . 'test_source.php';
        file_put_contents($testFile, '<?php class TestClass {}');

        $originalHash = HashUtility::hashFile($testFile);
        $this->assertNotFalse($originalHash);

        $attr = new AttributeInfo(
            className: 'TestClass',
            attributeName: 'TestAttribute',
            arguments: [],
            filePath: $testFile,
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestClass',
            ),
            fileHash: $originalHash,
        );

        // Cache with validation enabled
        $cache = new CompiledCache($this->tempPath, true, true);
        $cache->set('test', [$attr]);

        // Verify original is cached
        $loaded = $cache->get('test');
        $this->assertIsArray($loaded);
        $this->assertCount(1, $loaded);

        // Modify the file content
        file_put_contents($testFile, '<?php class TestClass { /* modified */ }');

        // Get cache again - should return null because hash changed
        $reloaded = $cache->get('test');
        $this->assertNull($reloaded);
    }

    /**
     * RED TEST: Test that validation is skipped when disabled
     */
    public function testValidationSkippedWhenDisabled(): void
    {
        $testFile = $this->tempPath . 'test_source2.php';
        file_put_contents($testFile, '<?php class TestClass {}');

        $originalHash = HashUtility::hashFile($testFile);
        $this->assertNotFalse($originalHash);

        $attr = new AttributeInfo(
            className: 'TestClass',
            attributeName: 'TestAttribute',
            arguments: [],
            filePath: $testFile,
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestClass',
            ),
            fileHash: $originalHash,
        );

        // Cache with validation DISABLED
        $cache = new CompiledCache($this->tempPath, true, false);
        $cache->set('test', [$attr]);

        // Modify the file
        file_put_contents($testFile, '<?php class TestClass { /* modified */ }');

        // Get cache again - should still return cached value
        $reloaded = $cache->get('test');
        $this->assertIsArray($reloaded);
        $this->assertCount(1, $reloaded);
    }

    /**
     * RED TEST: Test backward compatibility with entries without fileHash
     */
    public function testBackwardCompatibilityWithoutFileHash(): void
    {
        $attr = new AttributeInfo(
            className: 'App\\Controller\\TestController',
            attributeName: 'App\\Route',
            arguments: [],
            filePath: __FILE__,
            lineNumber: 10,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestController',
            ),
            fileHash: '', // Empty hash for backward compatibility
        );

        $cache = new CompiledCache($this->tempPath, true, true);
        $cache->set('test', [$attr]);

        $loaded = $cache->get('test');

        // Should not filter entries without hash
        $this->assertIsArray($loaded);
        $this->assertCount(1, $loaded);
    }

    public function testValidationHandlesHashFailureGracefully(): void
    {
        // Create a test file
        $testFile = $this->tempPath . 'test_file.php';
        $fileContent = '<?php class TestClass {}';
        file_put_contents($testFile, $fileContent);

        $fileHash = HashUtility::hashFile($testFile);
        $this->assertNotFalse($fileHash);

        // Create attribute with valid hash
        $attr = new AttributeInfo(
            className: 'TestClass',
            attributeName: 'TestAttribute',
            arguments: [],
            filePath: $testFile,
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestClass',
            ),
            fileHash: $fileHash,
        );

        $cache = new CompiledCache($this->tempPath, true, true);
        $cache->set('test', [$attr]);

        // Delete the file to trigger hash failure
        unlink($testFile);

        $loaded = $cache->get('test');

        // Should return null when file doesn't exist
        $this->assertNull($loaded);
    }

    public function testValidationOptimizesMultipleAttributesFromSameFile(): void
    {
        // Create a test file
        $testFile = $this->tempPath . 'shared_file.php';
        $fileContent = '<?php class TestClass {}';
        file_put_contents($testFile, $fileContent);

        $fileHash = HashUtility::hashFile($testFile);
        $this->assertNotFalse($fileHash);

        // Create multiple attributes from the same file
        $attr1 = new AttributeInfo(
            className: 'TestClass',
            attributeName: 'Attribute1',
            arguments: [],
            filePath: $testFile,
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'TestClass',
            ),
            fileHash: $fileHash,
        );

        $attr2 = new AttributeInfo(
            className: 'TestClass',
            attributeName: 'Attribute2',
            arguments: [],
            filePath: $testFile,
            lineNumber: 2,
            target: new AttributeTarget(
                type: AttributeTargetType::METHOD,
                targetName: 'testMethod',
                parentClass: 'TestClass',
            ),
            fileHash: $fileHash,
        );

        $cache = new CompiledCache($this->tempPath, true, true);
        $cache->set('test', [$attr1, $attr2]);

        $loaded = $cache->get('test');

        // Should successfully validate both attributes (file hash computed only once)
        $this->assertIsArray($loaded);
        $this->assertCount(2, $loaded);
    }

    public function testGeneratedCacheIncludesPluginName(): void
    {
        $attr = new AttributeInfo(
            className: 'TestPlugin\\Controller\\UsersController',
            attributeName: 'TestPlugin\\Attribute\\Route',
            arguments: ['path' => '/users'],
            filePath: '/path/to/plugin/TestPlugin/src/Controller/UsersController.php',
            lineNumber: 10,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'UsersController',
            ),
            fileHash: 'abc123',
            pluginName: 'TestPlugin',
        );

        $this->cache->set('plugin_test', [$attr]);

        // Check that the generated PHP code includes pluginName
        $safeKey = 'plugin_test';
        $cacheFile = $this->tempPath . $safeKey . '.php';
        $this->assertFileExists($cacheFile);

        $contents = file_get_contents($cacheFile);
        $this->assertNotFalse($contents, 'Failed to read cache file');
        $this->assertStringContainsString('pluginName:', $contents);
        $this->assertStringContainsString("'TestPlugin'", $contents);

        $loaded = $this->cache->get('plugin_test');
        $this->assertIsArray($loaded);
        $this->assertCount(1, $loaded);
        $this->assertEquals('TestPlugin', $loaded[0]->pluginName);
    }

    public function testGeneratedCacheHandlesNullPluginName(): void
    {
        $attr = new AttributeInfo(
            className: 'App\\Controller\\UsersController',
            attributeName: 'App\\Attribute\\Route',
            arguments: ['path' => '/users'],
            filePath: '/app/src/Controller/UsersController.php',
            lineNumber: 10,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'UsersController',
            ),
            fileHash: 'abc123',
        );

        $this->cache->set('app_test', [$attr]);
        $loaded = $this->cache->get('app_test');

        $this->assertIsArray($loaded);
        $this->assertCount(1, $loaded);
        $this->assertNull($loaded[0]->pluginName);
    }
}
