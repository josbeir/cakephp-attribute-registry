<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Command\AttributeInspectCommand;
use AttributeRegistry\Command\AttributeListCommand;
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
        $this->assertTrue($provider->provides(AttributeDiscoverCommand::class));
        $this->assertTrue($provider->provides(AttributeListCommand::class));
        $this->assertTrue($provider->provides(AttributeInspectCommand::class));
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

        $this->assertTrue($container->has(AttributeDiscoverCommand::class));
        $this->assertTrue($container->has(AttributeListCommand::class));
        $this->assertTrue($container->has(AttributeInspectCommand::class));
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

        $command = $container->get(AttributeDiscoverCommand::class);
        $this->assertInstanceOf(AttributeDiscoverCommand::class, $command);

        $listCommand = $container->get(AttributeListCommand::class);
        $this->assertInstanceOf(AttributeListCommand::class, $listCommand);

        $inspectCommand = $container->get(AttributeInspectCommand::class);
        $this->assertInstanceOf(AttributeInspectCommand::class, $inspectCommand);
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
