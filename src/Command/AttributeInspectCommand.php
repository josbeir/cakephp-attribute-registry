<?php
declare(strict_types=1);

namespace AttributeRegistry\Command;

use AttributeRegistry\Service\AttributeRegistry;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Command to inspect details of a specific attribute.
 */
class AttributeInspectCommand extends Command
{
    /**
     * @param \AttributeRegistry\Service\AttributeRegistry $registry Attribute registry service
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
        return 'attribute inspect';
    }

    /**
     * Get the command description.
     */
    public static function getDescription(): string
    {
        return 'Inspect detailed information about specific attributes.';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(static::getDescription())
            ->addArgument('attribute', [
                'required' => false,
                'help' => 'The attribute class name to inspect (partial match supported)',
            ])
            ->addOption('class', [
                'short' => 'c',
                'help' => 'Show all attributes on a specific class (partial match supported)',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $attributeName = $args->getArgument('attribute');
        $className = $args->getOption('class');

        if ($attributeName === null && $className === null) {
            $io->error('Please provide an attribute name or use --class option');

            return static::CODE_ERROR;
        }

        if ($attributeName !== null) {
            $attributes = $this->registry->findByAttribute($attributeName);
            $searchTerm = $attributeName;
            $searchType = 'attribute';
        } else {
            $attributes = $this->registry->findByClass((string)$className);
            $searchTerm = (string)$className;
            $searchType = 'class';
        }

        if ($attributes === []) {
            $io->error(sprintf('No attributes found matching %s "%s"', $searchType, $searchTerm));

            return static::CODE_ERROR;
        }

        $count = count($attributes);
        $io->out(sprintf('<info>Found %d attributes for %s "%s":</info>', $count, $searchType, $searchTerm));
        $io->out('');

        foreach ($attributes as $index => $attr) {
            $this->displayAttributeInfo($io, $attr, $index + 1);
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Display detailed information about an attribute.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \AttributeRegistry\ValueObject\AttributeInfo $attr Attribute info
     * @param int $index Display index
     */
    private function displayAttributeInfo(ConsoleIo $io, AttributeInfo $attr, int $index): void
    {
        $io->out(sprintf('<comment>%d. %s</comment>', $index, $attr->attributeName));
        $io->out(sprintf('   Class: %s', $attr->className));
        $io->out(sprintf('   Target: %s (%s)', $attr->target->targetName, $attr->target->type->value));
        $io->out(sprintf('   File: %s:%d', $attr->filePath, $attr->lineNumber));

        if ($attr->arguments !== []) {
            $io->out('   Arguments:');
            foreach ($attr->arguments as $key => $value) {
                $io->out(sprintf('     - %s: %s', $key, $this->formatValue($value)));
            }
        }

        $io->out('');
    }

    /**
     * Format a value for display.
     *
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string)$value;
    }
}
