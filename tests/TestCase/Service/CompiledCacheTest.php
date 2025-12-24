<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\CompiledCache;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Cake\TestSuite\TestCase;

class CompiledCacheTest extends TestCase
{
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
        $this->removeDirectory($this->tempPath);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function createTestAttribute(): AttributeInfo
    {
        return new AttributeInfo(
            className: 'App\\Controller\\UsersController',
            attributeName: 'App\\Route',
            arguments: ['path' => '/users'],
            filePath: '/app/src/Controller/UsersController.php',
            lineNumber: 15,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'UsersController',
            ),
            fileModTime: 1703347200,
        );
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
        $attr = $this->createTestAttribute();

        $cache->set('test_key', [$attr]);
        $result = $cache->get('test_key');

        $this->assertNull($result);
    }

    public function testSetReturnsFalseWhenDisabled(): void
    {
        $cache = new CompiledCache($this->tempPath, false);
        $attr = $this->createTestAttribute();

        $result = $cache->set('test_key', [$attr]);

        $this->assertFalse($result);
    }

    public function testSetAndGetSimpleAttribute(): void
    {
        $attr = $this->createTestAttribute();

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
        $this->assertEquals($attr->fileModTime, $getResult[0]->fileModTime);
        $this->assertEquals($attr->target->type, $getResult[0]->target->type);
        $this->assertEquals($attr->target->targetName, $getResult[0]->target->targetName);
        $this->assertEquals($attr->target->parentClass, $getResult[0]->target->parentClass);
    }

    public function testSetAndGetMultipleAttributes(): void
    {
        $attr1 = $this->createTestAttribute();
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
            fileModTime: 1703347300,
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
            fileModTime: 1703347200,
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
            fileModTime: 1703347200,
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
            fileModTime: 1703347200,
        );

        $this->cache->set('namespaces', [$attr]);
        $loaded = $this->cache->get('namespaces');

        $this->assertIsArray($loaded);
        $this->assertEquals($attr->className, $loaded[0]->className);
        $this->assertEquals($attr->attributeName, $loaded[0]->attributeName);
    }

    public function testDeleteRemovesCacheFile(): void
    {
        $attr = $this->createTestAttribute();
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
        $attr1 = $this->createTestAttribute();
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
        $attr = $this->createTestAttribute();
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
        $attr = $this->createTestAttribute();
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
        $attr1 = $this->createTestAttribute();
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
            fileModTime: 1703347300,
        );

        $this->cache->set('test', [$attr1]);
        $this->cache->set('test', [$attr2]);

        $result = $this->cache->get('test');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Different\\Class', $result[0]->className);
    }
}
