<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\Service\PluginLocator;
use Cake\Utility\Filesystem;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private string $testAppPath;

    private PathResolver $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testAppPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'attribute_registry_test_' . uniqid();
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

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertNotEmpty($paths);
    }

    public function testResolveRecursiveGlobPattern(): void
    {
        $patterns = ['src/**/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'TestController.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'TestModel.php', $paths);
    }

    public function testResolveMultiplePatterns(): void
    {
        $patterns = ['src/*.php', 'config/*.php'];
        $paths = iterator_to_array($this->pathResolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', $paths);
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
        $pluginPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'attribute_registry_plugin_' . uniqid();
        mkdir($pluginPath . DIRECTORY_SEPARATOR . 'src', 0755, true);
        file_put_contents($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php', "<?php\n// Plugin file");

        // Join paths with PATH_SEPARATOR like the plugin does
        $combinedPaths = $this->testAppPath . PATH_SEPARATOR . $pluginPath;
        $resolver = new PathResolver($combinedPaths);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        // Should find files from both base paths
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php', $paths);

        // Cleanup plugin path
        unlink($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php');
        rmdir($pluginPath . DIRECTORY_SEPARATOR . 'src');
        rmdir($pluginPath);
    }

    public function testPluginPathsNotLoadedOnConstruction(): void
    {
        $pluginLocator = $this->createMock(PluginLocator::class);
        $pluginLocator->expects($this->never())
            ->method('getEnabledPluginPaths');

        new PathResolver($this->testAppPath, $pluginLocator);
    }

    public function testPluginPathsLoadedOnFirstResolve(): void
    {
        $pluginLocator = $this->createMock(PluginLocator::class);
        $pluginLocator->expects($this->once())
            ->method('getEnabledPluginPaths')
            ->willReturn([]);

        $resolver = new PathResolver($this->testAppPath, $pluginLocator);

        // Invoke resolveAllPaths to trigger getEnabledPluginPaths()
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));
    }

    public function testPluginPathsOnlyLoadedOnce(): void
    {
        $pluginLocator = $this->createMock(PluginLocator::class);
        $pluginLocator->expects($this->once())
            ->method('getEnabledPluginPaths')
            ->willReturn([]);

        $resolver = new PathResolver($this->testAppPath, $pluginLocator);

        // Invoke resolveAllPaths multiple times
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));
        iterator_to_array($resolver->resolveAllPaths(['src/*.php']));

        // getEnabledPluginPaths() should only be invoked once
    }

    public function testPluginPathsMergedWithBasePath(): void
    {
        // Create a plugin path
        $pluginPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'attribute_registry_lazy_plugin_' . uniqid();
        mkdir($pluginPath . DIRECTORY_SEPARATOR . 'src', 0755, true);
        file_put_contents($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php', "<?php\n// Plugin file");

        $pluginLocator = $this->createMock(PluginLocator::class);
        $pluginLocator->expects($this->once())
            ->method('getEnabledPluginPaths')
            ->willReturn([$pluginPath]);

        $resolver = new PathResolver($this->testAppPath, $pluginLocator);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        // Should find files from both base path and lazily resolved plugin paths
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php', $paths);

        // Cleanup plugin path
        unlink($pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PluginClass.php');
        rmdir($pluginPath . DIRECTORY_SEPARATOR . 'src');
        rmdir($pluginPath);
    }

    public function testPathResolverWorksWithoutLazyCallback(): void
    {
        // Ensure backward compatibility - can be constructed without callback
        $resolver = new PathResolver($this->testAppPath);

        $patterns = ['src/*.php'];
        $paths = iterator_to_array($resolver->resolveAllPaths($patterns));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertNotEmpty($paths);
    }

    /**
     * RED TEST: Exclude specific directories using glob patterns
     */
    public function testExcludeDirectoriesWithGlobPattern(): void
    {
        // Create vendor and tmp directories
        mkdir($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor', 0755, true);
        mkdir($this->testAppPath . DIRECTORY_SEPARATOR . 'tmp', 0755, true);
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Test.php', '<?php');
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'Cache.php', '<?php');

        $resolver = new PathResolver($this->testAppPath, null, ['vendor/**', 'tmp/**']);
        $paths = iterator_to_array($resolver->resolveAllPaths(['**/*.php']));

        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Test.php', $paths);
        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'Cache.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
    }

    /**
     * RED TEST: Exclude by filename pattern
     */
    public function testExcludeFilesByPattern(): void
    {
        $resolver = new PathResolver($this->testAppPath, null, ['*Controller.php']);
        $paths = iterator_to_array($resolver->resolveAllPaths(['src/**/*.php']));

        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'TestController.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'TestModel.php', $paths);
    }

    /**
     * RED TEST: Multiple exclusion patterns
     */
    public function testMultipleExclusionPatterns(): void
    {
        $resolver = new PathResolver($this->testAppPath, null, ['*Controller.php', '**/Model/**']);
        $paths = iterator_to_array($resolver->resolveAllPaths(['src/**/*.php']));

        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'TestController.php', $paths);
        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'TestModel.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
    }

    /**
     * RED TEST: No exclusions when empty array provided
     */
    public function testNoExclusionsWithEmptyArray(): void
    {
        $resolver = new PathResolver($this->testAppPath, null, []);
        $paths = iterator_to_array($resolver->resolveAllPaths(['src/**/*.php']));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'TestController.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'TestModel.php', $paths);
    }

    /**
     * Test pattern with just filename (no directory prefix)
     */
    public function testResolvePatternWithJustFilename(): void
    {
        // Create files in base directory
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'BaseFile.php', '<?php');
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'OtherFile.txt', 'text');

        $resolver = new PathResolver($this->testAppPath);
        $paths = iterator_to_array($resolver->resolveAllPaths(['*.php']));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'BaseFile.php', $paths);
        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'OtherFile.txt', $paths);
    }

    /**
     * Test recursive pattern with no suffix
     */
    public function testResolveRecursivePatternWithNoSuffix(): void
    {
        $resolver = new PathResolver($this->testAppPath);
        $paths = iterator_to_array($resolver->resolveAllPaths(['src/**']));

        // Should find all files (not just .php)
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'TestController.php', $paths);
    }

    /**
     * Test pattern starting with ** (scan from root)
     */
    public function testResolvePatternStartingWithDoubleStarFromRoot(): void
    {
        $resolver = new PathResolver($this->testAppPath);
        $paths = iterator_to_array($resolver->resolveAllPaths(['**/*.php']));

        // Should find files from all directories
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', $paths);
    }

    /**
     * Test suffix filter actually filters non-matching files
     */
    public function testSuffixFilterExcludesNonMatchingFiles(): void
    {
        // Create files with different extensions
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Test.txt', 'text');
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Test.php', '<?php');

        $resolver = new PathResolver($this->testAppPath);
        $paths = iterator_to_array($resolver->resolveAllPaths(['src/*.php']));

        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Test.php', $paths);
        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Test.txt', $paths);
    }

    /**
     * Test exclusion pattern matches directory exactly
     */
    public function testExcludeDirectoryExactMatch(): void
    {
        mkdir($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor', 0755, true);
        file_put_contents($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Test.php', '<?php');

        // Test that 'vendor/**' excludes the vendor directory itself
        $resolver = new PathResolver($this->testAppPath, null, ['vendor/**']);
        $paths = iterator_to_array($resolver->resolveAllPaths(['**/*.php']));

        $this->assertNotContains($this->testAppPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Test.php', $paths);
        $this->assertContains($this->testAppPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TestClass.php', $paths);
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
            $fullPath = $this->testAppPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, "<?php\n// Test file");
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            (new Filesystem())->deleteDir($dir);
        }
    }
}
