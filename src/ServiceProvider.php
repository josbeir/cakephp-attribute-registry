<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Command\AttributeInspectCommand;
use AttributeRegistry\Command\AttributeListCommand;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeRegistry;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin;
use Cake\Core\PluginInterface;
use Cake\Core\ServiceProvider as CakeServiceProvider;

/**
 * Service provider for AttributeRegistry plugin.
 */
class ServiceProvider extends CakeServiceProvider
{
    /**
     * @var array<string>
     */
    protected array $provides = [
        AttributeRegistry::class,
        AttributeDiscoverCommand::class,
        AttributeListCommand::class,
        AttributeInspectCommand::class,
    ];

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(AttributeRegistry::class, function (): AttributeRegistry {
            return $this->createRegistry();
        });

        $container->add(AttributeDiscoverCommand::class, function () use ($container): AttributeDiscoverCommand {
            /** @var \AttributeRegistry\Service\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeDiscoverCommand($registry);
        });

        $container->add(AttributeListCommand::class, function () use ($container): AttributeListCommand {
            /** @var \AttributeRegistry\Service\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeListCommand($registry);
        });

        $container->add(AttributeInspectCommand::class, function () use ($container): AttributeInspectCommand {
            /** @var \AttributeRegistry\Service\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeInspectCommand($registry);
        });
    }

    /**
     * Create and configure the AttributeRegistry service.
     *
     * @return \AttributeRegistry\Service\AttributeRegistry
     */
    private function createRegistry(): AttributeRegistry
    {
        $config = (array)Configure::read('AttributeRegistry');
        $scannerConfig = (array)$config['scanner'];
        $cacheConfig = (array)$config['cache'];

        $pathResolver = new PathResolver(implode(PATH_SEPARATOR, $this->resolveAllPaths()));
        $cache = new AttributeCache(
            (string)$cacheConfig['config'],
            (bool)($cacheConfig['enabled'] ?? true),
        );
        $parser = new AttributeParser();

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => (array)$scannerConfig['paths'],
                'exclude_paths' => (array)$scannerConfig['exclude_paths'],
                'max_file_size' => (int)$scannerConfig['max_file_size'],
            ],
        );

        return new AttributeRegistry($scanner, $cache);
    }

    /**
     * Resolve all base paths from app + loaded plugins.
     *
     * @return array<string> Resolved base paths
     */
    private function resolveAllPaths(): array
    {
        $basePaths = [];
        // App root
        $basePaths[] = ROOT;
        // Plugin paths
        $plugins = Plugin::getCollection();
        /** @var \Cake\Core\PluginInterface $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin instanceof PluginInterface) {
                $basePaths[] = $plugin->getPath();
            }
        }

        return $basePaths;
    }
}
