<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Command\AttributeInspectCommand;
use AttributeRegistry\Command\AttributeListCommand;
use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\RouteBuilder;

/**
 * Plugin class for AttributeRegistry.
 *
 * Provides PHP attribute discovery and caching functionality.
 */
class AttributeRegistryPlugin extends BasePlugin
{
    /**
     * @param \Cake\Core\PluginApplicationInterface<\Cake\Event\EventManager> $app Application instance
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        $configFile = $this->getConfigPath() . 'app_attribute_registry.php';
        if (file_exists($configFile)) {
            Configure::load('AttributeRegistry.app_attribute_registry');
        }

        $this->registerCacheConfig();
        $this->registerDebugKitPanel();
    }

    /**
     * Register the attribute_registry cache configuration.
     *
     * Uses CACHE_ATTRIBUTE_REGISTRY_URL env var if set,
     * otherwise uses a file-based cache with long duration.
     */
    private function registerCacheConfig(): void
    {
        if (Cache::getConfig('attribute_registry') !== null) {
            return;
        }

        Cache::setConfig('attribute_registry', [
            'className' => FileEngine::class,
            'path' => CACHE . 'attribute_registry' . DS,
            'duration' => '+1 month',
            'prefix' => 'attr_',
            'url' => env('CACHE_ATTRIBUTE_REGISTRY_URL'),
        ]);
    }

    /**
     * Register the DebugKit panel if DebugKit is loaded.
     */
    private function registerDebugKitPanel(): void
    {
        if (!Plugin::isLoaded('DebugKit')) {
            return;
        }

        $panels = Configure::read('DebugKit.panels', []);
        $panels['AttributeRegistry'] = 'AttributeRegistry.AttributeRegistry';
        Configure::write('DebugKit.panels', $panels);
    }

    /**
     * @inheritDoc
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('AttributeRegistry', ['path' => '/attribute-registry'], function (RouteBuilder $builder): void {
            $builder->connect('/debug-kit/discover', [
                'controller' => 'DebugKit',
                'action' => 'discover',
            ]);
        });
        parent::routes($routes);
    }

    /**
     * @inheritDoc
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('attribute discover', AttributeDiscoverCommand::class);
        $commands->add('attribute list', AttributeListCommand::class);
        $commands->add('attribute inspect', AttributeInspectCommand::class);

        return $commands;
    }

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new ServiceProvider());
    }
}
