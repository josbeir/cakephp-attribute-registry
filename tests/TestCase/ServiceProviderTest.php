<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributesCacheCommand;
use AttributeRegistry\Command\AttributesInspectCommand;
use AttributeRegistry\Command\AttributesListCommand;
use AttributeRegistry\ServiceProvider;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\TestSuite\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::setConfig('attribute_test', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        // Set up plugin configuration
        Configure::write('AttributeRegistry', [
            'cache' => [
                'enabled' => true,
                'config' => 'attribute_test',
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
        Cache::clear('attribute_test');
        Cache::drop('attribute_test');
        Configure::delete('AttributeRegistry');
        AttributeRegistry::setInstance(null);
    }

    public function testServiceProviderCanBeCreated(): void
    {
        $provider = new ServiceProvider();
        $this->assertInstanceOf(ServiceProvider::class, $provider);
    }

    public function testServiceProviderProvidesAttributeRegistry(): void
    {
        $provider = new ServiceProvider();
        $this->assertTrue($provider->provides(AttributeRegistry::class));
    }

    public function testServiceProviderProvidesCommands(): void
    {
        $provider = new ServiceProvider();
        $this->assertTrue($provider->provides(AttributesCacheCommand::class));
        $this->assertTrue($provider->provides(AttributesListCommand::class));
        $this->assertTrue($provider->provides(AttributesInspectCommand::class));
    }

    public function testServiceProviderRegistersAttributeRegistry(): void
    {
        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());

        $this->assertTrue($container->has(AttributeRegistry::class));
    }

    public function testServiceProviderRegistersCommands(): void
    {
        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());

        $this->assertTrue($container->has(AttributesCacheCommand::class));
        $this->assertTrue($container->has(AttributesListCommand::class));
        $this->assertTrue($container->has(AttributesInspectCommand::class));
    }

    public function testAttributeRegistryIsShared(): void
    {
        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());

        $registry1 = $container->get(AttributeRegistry::class);
        $registry2 = $container->get(AttributeRegistry::class);

        $this->assertSame($registry1, $registry2);
    }

    public function testCommandsReceiveRegistryInstance(): void
    {
        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());

        $command = $container->get(AttributesCacheCommand::class);
        $this->assertInstanceOf(AttributesCacheCommand::class, $command);

        $listCommand = $container->get(AttributesListCommand::class);
        $this->assertInstanceOf(AttributesListCommand::class, $listCommand);

        $inspectCommand = $container->get(AttributesInspectCommand::class);
        $this->assertInstanceOf(AttributesInspectCommand::class, $inspectCommand);
    }

    public function testServiceProviderRespectsDisabledCache(): void
    {
        Configure::write('AttributeRegistry.cache.enabled', false);

        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());

        $registry = $container->get(AttributeRegistry::class);
        $this->assertFalse($registry->isCacheEnabled());
    }
}
