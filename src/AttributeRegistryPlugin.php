<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Command\AttributeInspectCommand;
use AttributeRegistry\Command\AttributeListCommand;
use Cake\Command\CacheClearallCommand;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\Event;
use Cake\Event\EventManagerInterface;
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

        $configFile = $this->getConfigPath() . 'attribute_registry.php';
        if (file_exists($configFile)) {
            Configure::load('AttributeRegistry.attribute_registry');
        }

        $this->registerDebugKitPanel();
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
        $panels['AttributeRegistry.AttributeRegistry'] = true;
        Configure::write('DebugKit.panels', $panels);
    }

    /**
     * @inheritDoc
     */
    public function routes(RouteBuilder $routes): void
    {
        if (Plugin::isLoaded('DebugKit')) {
            $routes->plugin(
                'AttributeRegistry',
                ['path' => '/attribute-registry'],
                function (RouteBuilder $builder): void {
                    $builder->setExtensions(['json']);
                    $builder->connect(
                        '/debug-kit/discover',
                        ['controller' => 'DebugKit', 'action' => 'discover'],
                    );
                },
            );
        }

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

    /**
     * @inheritDoc
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        if (!Configure::read('AttributeRegistry.disableCacheClearListener', false)) {
            $eventManager->on('Command.afterExecute', function (Event $event): void {
                $command = $event->getSubject();

                // Clear AttributeRegistry cache when cache:clear_all is run
                if ($command instanceof CacheClearallCommand) {
                    $command->executeCommand(
                        AttributeDiscoverCommand::class,
                        ['clear-cache' => true, 'no-discover' => true],
                    );
                }
            });
        }

        return $eventManager;
    }
}
