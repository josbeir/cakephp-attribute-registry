<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistryPlugin;
use AttributeRegistry\Service\AttributeRegistry;
use Cake\Console\CommandCollection;
use Cake\Core\Container;
use Cake\TestSuite\TestCase;

class AttributeRegistryPluginTest extends TestCase
{
    private AttributeRegistryPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new AttributeRegistryPlugin();
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
}
