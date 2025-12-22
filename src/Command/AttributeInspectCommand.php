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
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Inspect details of a specific attribute')
            ->addArgument('attribute', [
                'required' => true,
                'help' => 'The attribute class name to inspect',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $attributeName = (string)$args->getArgument('attribute');
        $attributes = $this->registry->findByAttribute($attributeName);

        if ($attributes === []) {
            $io->error(sprintf('No attributes found matching "%s"', $attributeName));

            return static::CODE_ERROR;
        }

        $io->out(sprintf('<info>Found %d usages of "%s":</info>', count($attributes), $attributeName));
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
