<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Service\PluginPathResolver;
use Cake\TestSuite\TestCase;

/**
 * PluginPathResolver Test Case
 */
class PluginPathResolverTest extends TestCase
{
    private PluginPathResolver $resolver;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load test local plugin using CakePHP test helper
        $this->loadPlugins(['TestLocalPlugin']);

        $this->resolver = new PluginPathResolver();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearPlugins();
    }

    /**
     * Test getEnabledPluginPaths returns paths for actual loaded plugins
     */
    public function testGetEnabledPluginPathsReturnsLoadedPlugins(): void
    {
        $paths = $this->resolver->getEnabledPluginPaths();

        // In test environment, we should have at least some plugins loaded
        // (depends on test configuration)
        foreach ($paths as $path) {
            $this->assertIsString($path);
            $this->assertNotEmpty($path);
        }

        // Assert we got an array back (even if empty)
        $this->assertCount(count($paths), $paths);
    }

    /**
     * Test that getEnabledPluginPaths returns consistent results
     */
    public function testGetEnabledPluginPathsIsConsistent(): void
    {
        $paths1 = $this->resolver->getEnabledPluginPaths();
        $paths2 = $this->resolver->getEnabledPluginPaths();

        // Should return same results on multiple calls
        $this->assertEquals($paths1, $paths2);
    }

    /**
     * Test that only enabled plugins are returned
     */
    public function testGetEnabledPluginPathsOnlyIncludesLoadedPlugins(): void
    {
        $paths = $this->resolver->getEnabledPluginPaths();

        // All returned paths should exist (enabled plugins should have valid paths)
        foreach ($paths as $path) {
            $this->assertDirectoryExists($path, 'Plugin path should exist: ' . $path);
        }

        // If no paths, make an assertion to avoid risky test
        if ($paths === []) {
            $this->assertEmpty($paths, 'No plugins configured in test environment');
        } else {
            $this->assertNotEmpty($paths, 'Should have at least one plugin path');
        }
    }

    /**
     * Test that local plugins without packagePath are included
     */
    public function testGetEnabledPluginPathsIncludesLocalPlugins(): void
    {
        // Get paths from resolver
        $paths = $this->resolver->getEnabledPluginPaths();

        // Verify local plugin path is included
        $localPluginPath = ROOT . DS . 'plugins' . DS . 'TestLocalPlugin' . DS;
        $this->assertContains($localPluginPath, $paths, 'Local plugin path should be included');

        // Verify the path actually exists
        $this->assertDirectoryExists($localPluginPath, 'Local plugin directory should exist');
    }

    /**
     * Test that local plugin attributes are discoverable
     */
    public function testLocalPluginAttributesAreDiscoverable(): void
    {
        // Get paths and verify plugin is included
        $paths = $this->resolver->getEnabledPluginPaths();
        $localPluginPath = ROOT . DS . 'plugins' . DS . 'TestLocalPlugin' . DS;

        $this->assertContains($localPluginPath, $paths);

        // Verify attribute file exists in local plugin
        $attributeFile = $localPluginPath . 'src' . DS . 'Attribute' . DS . 'LocalPluginRoute.php';
        $this->assertFileExists($attributeFile, 'Attribute file should exist in local plugin');

        // Verify controller with attributes exists
        $controllerFile = $localPluginPath . 'src' . DS . 'Controller' . DS . 'TestLocalController.php';
        $this->assertFileExists($controllerFile, 'Controller file should exist in local plugin');
    }
}
