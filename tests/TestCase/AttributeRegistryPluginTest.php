<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\AttributeRegistryPlugin;
use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Container;
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
}
