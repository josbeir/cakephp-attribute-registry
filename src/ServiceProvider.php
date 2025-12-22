<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Command\AttributeInspectCommand;
use AttributeRegistry\Command\AttributeListCommand;
use Cake\Core\ContainerInterface;
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
            return AttributeRegistry::getInstance();
        });

        $container->add(AttributeDiscoverCommand::class, function () use ($container): AttributeDiscoverCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeDiscoverCommand($registry);
        });

        $container->add(AttributeListCommand::class, function () use ($container): AttributeListCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeListCommand($registry);
        });

        $container->add(AttributeInspectCommand::class, function () use ($container): AttributeInspectCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributeInspectCommand($registry);
        });
    }
}
