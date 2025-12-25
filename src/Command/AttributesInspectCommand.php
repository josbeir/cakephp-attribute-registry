<?php
declare(strict_types=1);

namespace AttributeRegistry\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\ValueObject\AttributeInfo;
use BackedEnum;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use UnitEnum;

/**
 * Command to inspect details of a specific attribute.
 */
class AttributesInspectCommand extends Command
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
        return 'attributes inspect';
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

        if ($attr->pluginName !== null) {
            $io->out(sprintf('   Plugin: %s', $attr->pluginName));
        }

        $io->out(sprintf('   Target: %s (%s)', $attr->target->targetName, $attr->target->type->value));

        if ($attr->target->parentClass !== null) {
            $io->out(sprintf('   Parent Class: %s', $attr->target->parentClass));
        }

        $io->out(sprintf('   File: %s:%d', $attr->filePath, $attr->lineNumber));
        $io->out(sprintf('   File Hash: %s', $attr->fileHash));

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

        if ($value instanceof BackedEnum) {
            return (string)$value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return (string)$value;
    }
}
