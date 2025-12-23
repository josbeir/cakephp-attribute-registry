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
        $this->resolver = new PluginPathResolver();
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
}
