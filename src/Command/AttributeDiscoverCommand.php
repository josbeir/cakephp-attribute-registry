<?php
declare(strict_types=1);

namespace AttributeRegistry\Command;

use AttributeRegistry\AttributeRegistry;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Command to discover and cache all attributes.
 */
class AttributeDiscoverCommand extends Command
{
    /**
     * @param \AttributeRegistry\AttributeRegistry $registry Attribute registry
     */
    public function __construct(
        private readonly AttributeRegistry $registry,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'attribute discover';
    }

    /**
     * Get the command description.
     */
    public static function getDescription(): string
    {
        return 'Discover and cache all PHP attributes in the application.';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(static::getDescription());
        $parser->addOption('no-clear-cache', [
            'boolean' => true,
            'default' => false,
            'help' => 'Skip clearing the attribute cache before discovering',
        ]);
        $parser->addOption('no-discover', [
            'boolean' => true,
            'default' => false,
            'help' => 'Only clear cache without discovering attributes',
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if (!$this->registry->isCacheEnabled()) {
            $io->warning('Cache is disabled. Attributes will be re-discovered on every request.');
        }

        // Clear cache by default unless --no-clear-cache is set
        if (!$args->getOption('no-clear-cache')) {
            $io->out('<info>Clearing attribute cache...</info>');
            $this->registry->clearCache();
        }

        // Only discover if --no-discover is not set
        if (!$args->getOption('no-discover')) {
            $io->out('<info>Discovering attributes...</info>');

            $startTime = microtime(true);
            $attributes = $this->registry->discover();
            $elapsed = round(microtime(true) - $startTime, 3);

            $io->success(sprintf(
                'Discovered %d attributes in %ss',
                count($attributes),
                $elapsed,
            ));
        }

        return static::CODE_SUCCESS;
    }
}
