<?php
declare(strict_types=1);

namespace AttributeRegistry\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Service\AttributeCacheValidator;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Command to manage attribute cache.
 */
class AttributesCacheCommand extends Command
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
        return 'attributes cache';
    }

    /**
     * Get the command description.
     */
    public static function getDescription(): string
    {
        return 'Manage attribute cache (clear and rebuild).';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(static::getDescription());
        $parser->addOption('no-clear', [
            'boolean' => true,
            'default' => false,
            'help' => 'Skip clearing the cache before discovering',
        ]);
        $parser->addOption('clear-only', [
            'boolean' => true,
            'default' => false,
            'help' => 'Only clear cache without discovering attributes',
        ]);
        $parser->addOption('validate', [
            'boolean' => true,
            'default' => false,
            'help' => 'Validate cache integrity after building',
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

        // Clear cache by default unless --no-clear is set
        if (!$args->getOption('no-clear')) {
            $io->out('<info>Clearing attribute cache...</info>');
            $this->registry->clearCache();
        }

        // Only discover if --clear-only is not set
        if (!$args->getOption('clear-only')) {
            $io->out('<info>Discovering attributes...</info>');

            $startTime = microtime(true);
            $attributes = $this->registry->discover();
            $elapsed = round(microtime(true) - $startTime, 3);

            $io->success(sprintf(
                'Discovered %d attributes in %ss',
                count($attributes),
                $elapsed,
            ));

            // Validate cache if --validate option is set
            if ($args->getOption('validate')) {
                $io->out('<info>Validating cache integrity...</info>');
                $validator = new AttributeCacheValidator($this->registry);
                $result = $validator->validate();

                if ($result->valid) {
                    $io->success(sprintf(
                        'Cache validation passed: %d attributes, %d files',
                        $result->totalAttributes,
                        $result->totalFiles,
                    ));
                } else {
                    $io->error('Cache validation failed:');
                    foreach ($result->errors as $error) {
                        $io->err('  - ' . $error);
                    }

                    return static::CODE_ERROR;
                }
            }
        }

        return static::CODE_SUCCESS;
    }
}
