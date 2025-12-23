<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Service\PathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private string $testAppPath;

    private PathResolver $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testAppPath = sys_get_temp_dir() . '/attribute_registry_test_' . uniqid();
        mkdir($this->testAppPath, 0755, true);

        $this->pathResolver = new PathResolver($this->testAppPath);

        // Create test directory structure
        $this->createTestStructure();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testAppPath);
    }

    public function testPathResolverCanBeCreated(): void
    {
        $pathResolver = new PathResolver('/some/path');
        $this->assertInstanceOf(PathResolver::class, $pathResolver);
    }

    public function testResolveSimpleGlobPattern(): void
    {
        $patterns = ['src/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertNotEmpty($paths);
    }

    public function testResolveRecursiveGlobPattern(): void
    {
        $patterns = ['src/**/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertContains($this->testAppPath . '/src/Controller/TestController.php', $paths);
        $this->assertContains($this->testAppPath . '/src/Model/TestModel.php', $paths);
    }

    public function testResolveMultiplePatterns(): void
    {
        $patterns = ['src/*.php', 'config/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertContains($this->testAppPath . '/config/app.php', $paths);
    }

    public function testResolveNonExistentPath(): void
    {
        $patterns = ['nonexistent/**/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertEmpty($paths);
    }

    public function testResolveMultipleBasePaths(): void
    {
        // Create a second base path simulating a plugin
        $pluginPath = sys_get_temp_dir() . '/attribute_registry_plugin_' . uniqid();
        mkdir($pluginPath . '/src', 0755, true);
        file_put_contents($pluginPath . '/src/PluginClass.php', "<?php\n// Plugin file");

        // Join paths with PATH_SEPARATOR like the plugin does
        $combinedPaths = $this->testAppPath . PATH_SEPARATOR . $pluginPath;
        $resolver = new PathResolver($combinedPaths);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        // Should find files from both base paths
        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertContains($pluginPath . '/src/PluginClass.php', $paths);

        // Cleanup plugin path
        unlink($pluginPath . '/src/PluginClass.php');
        rmdir($pluginPath . '/src');
        rmdir($pluginPath);
    }

    public function testLazyPluginPathCallbackIsNotInvokedOnConstruction(): void
    {
        $callbackInvoked = false;
        $callback = function () use (&$callbackInvoked): array {
            $callbackInvoked = true;

            return ['/some/plugin/path'];
        };

        new PathResolver($this->testAppPath, $callback);

        // Callback should NOT be invoked during construction
        $this->assertFalse($callbackInvoked, 'Plugin path callback should not be invoked during construction');
    }

    public function testLazyPluginPathCallbackIsInvokedOnFirstResolve(): void
    {
        $callbackInvoked = false;
        $callback = function () use (&$callbackInvoked): array {
            $callbackInvoked = true;

            return [];
        };

        $resolver = new PathResolver($this->testAppPath, $callback);

        // Invoke resolveAllPaths to trigger callback
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));

        // Callback should be invoked on first resolve
        $this->assertTrue($callbackInvoked, 'Plugin path callback should be invoked on first path resolution');
    }

    public function testLazyPluginPathCallbackIsOnlyInvokedOnce(): void
    {
        $callbackInvokeCount = 0;
        $callback = function () use (&$callbackInvokeCount): array {
            $callbackInvokeCount++;

            return [];
        };

        $resolver = new PathResolver($this->testAppPath, $callback);

        // Invoke resolveAllPaths multiple times
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));

        // Callback should only be invoked once
        $this->assertSame(1, $callbackInvokeCount, 'Plugin path callback should only be invoked once');
    }

    public function testLazyPluginPathsAreMergedWithBasePath(): void
    {
        // Create a plugin path
        $pluginPath = sys_get_temp_dir() . '/attribute_registry_lazy_plugin_' . uniqid();
        mkdir($pluginPath . '/src', 0755, true);
        file_put_contents($pluginPath . '/src/PluginClass.php', "<?php\n// Plugin file");

        $callback = fn(): array => [$pluginPath];

        $resolver = new PathResolver($this->testAppPath, $callback);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        // Should find files from both base path and lazily resolved plugin paths
        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertContains($pluginPath . '/src/PluginClass.php', $paths);

        // Cleanup plugin path
        unlink($pluginPath . '/src/PluginClass.php');
        rmdir($pluginPath . '/src');
        rmdir($pluginPath);
    }

    public function testPathResolverWorksWithoutLazyCallback(): void
    {
        // Ensure backward compatibility - can be constructed without callback
        $resolver = new PathResolver($this->testAppPath);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . '/src/TestClass.php', $paths);
        $this->assertNotEmpty($paths);
    }

    private function createTestStructure(): void
    {
        $structure = [
            'src/TestClass.php',
            'src/Controller/TestController.php',
            'src/Model/TestModel.php',
            'config/app.php',
            'config/database.php',
            'templates/test.latte',
        ];

        foreach ($structure as $file) {
            $fullPath = $this->testAppPath . '/' . $file;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, "<?php\n// Test file");
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
