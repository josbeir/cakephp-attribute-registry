<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Command\AttributesCacheCommand;
use AttributeRegistry\Command\AttributesInspectCommand;
use AttributeRegistry\Command\AttributesListCommand;
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
        AttributesCacheCommand::class,
        AttributesListCommand::class,
        AttributesInspectCommand::class,
    ];

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(AttributeRegistry::class, function (): AttributeRegistry {
            return AttributeRegistry::getInstance();
        });

        $container->add(AttributesCacheCommand::class, function () use ($container): AttributesCacheCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributesCacheCommand($registry);
        });

        $container->add(AttributesListCommand::class, function () use ($container): AttributesListCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributesListCommand($registry);
        });

        $container->add(AttributesInspectCommand::class, function () use ($container): AttributesInspectCommand {
            /** @var \AttributeRegistry\AttributeRegistry $registry */
            $registry = $container->get(AttributeRegistry::class);

            return new AttributesInspectCommand($registry);
        });
    }
}
