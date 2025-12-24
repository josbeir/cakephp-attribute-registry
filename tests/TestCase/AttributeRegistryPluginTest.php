<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\AttributeRegistryPlugin;
use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\PluginApplicationInterface;
use Cake\TestSuite\TestCase;

class AttributeRegistryPluginTest extends TestCase
{
    private AttributeRegistryPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new AttributeRegistryPlugin();

        // Set up required configuration for tests
        Configure::write('AttributeRegistry', [
            'cache' => [
                'enabled' => true,
            ],
            'scanner' => [
                'paths' => ['src/**/*.php'],
                'exclude_paths' => ['vendor/**'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('AttributeRegistry');
    }

    public function testPluginCanBeCreated(): void
    {
        $this->assertInstanceOf(AttributeRegistryPlugin::class, $this->plugin);
    }

    public function testConsoleRegistersCommands(): void
    {
        $commands = new CommandCollection();
        $result = $this->plugin->console($commands);

        $this->assertInstanceOf(CommandCollection::class, $result);
        $this->assertTrue($result->has('attribute discover'));
        $this->assertTrue($result->has('attribute list'));
        $this->assertTrue($result->has('attribute inspect'));
    }

    public function testServicesRegistersContainer(): void
    {
        $container = new Container();
        $this->plugin->services($container);

        $this->assertTrue($container->has(AttributeRegistry::class));
    }

    public function testPluginHasCorrectPath(): void
    {
        $path = $this->plugin->getPath();
        $this->assertStringContainsString('cakephp-attribute-registry', $path);
    }

    public function testPluginHasCorrectConfigPath(): void
    {
        $configPath = $this->plugin->getConfigPath();
        $this->assertStringContainsString('config', $configPath);
    }

    public function testBootstrapPreservesUserConfiguration(): void
    {
        // Set user configuration BEFORE bootstrap
        Configure::write('AttributeRegistry', [
            'cache' => [
                'enabled' => false, // User wants caching disabled
                'path' => '/custom/cache/path',
                'validateFiles' => true,
            ],
            'scanner' => [
                'paths' => ['custom/**/*.php'], // User custom paths
                'exclude_paths' => ['custom_exclude/**'],
            ],
        ]);

        // Create a stub application (no expectations needed)
        $app = $this->createStub(PluginApplicationInterface::class);

        // Bootstrap should NOT overwrite user settings
        $this->plugin->bootstrap($app);

        // Verify user configuration is preserved
        $config = Configure::read('AttributeRegistry');

        $this->assertFalse($config['cache']['enabled'], 'User cache.enabled should be preserved');
        $this->assertSame('/custom/cache/path', $config['cache']['path'], 'User cache.path should be preserved');
        $this->assertTrue($config['cache']['validateFiles'], 'User cache.validateFiles should be preserved');
        $this->assertSame(['custom/**/*.php'], $config['scanner']['paths'], 'User scanner.paths should be preserved');
        $this->assertSame(['custom_exclude/**'], $config['scanner']['exclude_paths'], 'User scanner.exclude_paths should be preserved');
    }

    public function testBootstrapMergesDefaultsWithUserConfiguration(): void
    {
        // Set PARTIAL user configuration
        Configure::write('AttributeRegistry', [
            'cache' => [
                'enabled' => false, // User only sets this
            ],
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);

        $this->plugin->bootstrap($app);

        $config = Configure::read('AttributeRegistry');

        // User setting preserved
        $this->assertFalse($config['cache']['enabled']);

        // Plugin defaults should fill in missing values
        $this->assertArrayHasKey('validateFiles', $config['cache']);
        $this->assertArrayHasKey('scanner', $config);
        $this->assertArrayHasKey('paths', $config['scanner']);
        $this->assertNotEmpty($config['scanner']['paths']);
    }

    public function testBootstrapWithNoUserConfiguration(): void
    {
        // Clear all configuration
        Configure::delete('AttributeRegistry');

        $app = $this->createStub(PluginApplicationInterface::class);

        $this->plugin->bootstrap($app);

        $config = Configure::read('AttributeRegistry');

        // Should have all plugin defaults
        $this->assertArrayHasKey('cache', $config);
        $this->assertArrayHasKey('scanner', $config);
        $this->assertTrue($config['cache']['enabled'], 'Default cache.enabled should be true');
        $this->assertIsArray($config['scanner']['paths']);
    }

    public function testBootstrapWorksWhenConfigFileDoesNotExist(): void
    {
        // Create a test plugin with a non-existent config path
        $plugin = new class extends AttributeRegistryPlugin {
            public function getConfigPath(): string
            {
                return '/non/existent/path/';
            }
        };

        Configure::delete('AttributeRegistry');
        Configure::write('AttributeRegistry', [
            'cache' => ['enabled' => true],
        ]);

        $app = $this->createStub(PluginApplicationInterface::class);

        // Should not throw an exception
        $plugin->bootstrap($app);

        // Configuration should still be present
        $config = Configure::read('AttributeRegistry');
        $this->assertNotNull($config);
        $this->assertTrue($config['cache']['enabled']);
    }
}
