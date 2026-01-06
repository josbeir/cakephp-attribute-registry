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

        $container->add(AttributesCacheCommand::class)
            ->addArgument(AttributeRegistry::class);

        $container->add(AttributesListCommand::class)
            ->addArgument(AttributeRegistry::class);

        $container->add(AttributesInspectCommand::class)
            ->addArgument(AttributeRegistry::class);
    }
}
