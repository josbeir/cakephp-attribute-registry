<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\AttributeRegistryPlugin;
use Cake\Cache\Cache;
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
                'config' => 'attribute_registry',
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

        // Clean up cache config if it was set
        if (Cache::getConfig('attribute_registry') !== null) {
            Cache::drop('attribute_registry');
        }

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

    public function testBootstrapRegistersCacheConfig(): void
    {
        // Ensure no cache config exists
        if (Cache::getConfig('attribute_registry') !== null) {
            Cache::drop('attribute_registry');
        }

        // Create a stub app (no expectations needed)
        $app = $this->createStub(PluginApplicationInterface::class);

        // Bootstrap should register cache config
        $this->plugin->bootstrap($app);

        $this->assertNotNull(Cache::getConfig('attribute_registry'));
    }

    public function testBootstrapSkipsExistingCacheConfig(): void
    {
        // Set up an existing cache config
        Cache::setConfig('attribute_registry', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $originalConfig = Cache::getConfig('attribute_registry');

        // Create a stub app (no expectations needed)
        $app = $this->createStub(PluginApplicationInterface::class);

        // Bootstrap should not overwrite existing config
        $this->plugin->bootstrap($app);

        $this->assertSame($originalConfig, Cache::getConfig('attribute_registry'));
    }
}
