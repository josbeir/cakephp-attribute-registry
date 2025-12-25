<?php
declare(strict_types=1);

namespace AttributeRegistry\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Enum\AttributeTargetType;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Command to list discovered attributes.
 */
class AttributesListCommand extends Command
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
        return 'attributes list';
    }

    /**
     * Get the command description.
     */
    public static function getDescription(): string
    {
        return 'List discovered PHP attributes in a table format.';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(static::getDescription())
            ->addOption('attribute', [
                'short' => 'a',
                'help' => 'Filter by attribute name (partial match supported)',
            ])
            ->addOption('class', [
                'short' => 'c',
                'help' => 'Filter by class name (partial match supported)',
            ])
            ->addOption('type', [
                'short' => 't',
                'help' => 'Filter by target type (class, method, property, parameter, constant)',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $attributes = $this->getFilteredAttributes($args);

        if ($attributes === []) {
            $io->warning('No attributes found matching the criteria.');

            return static::CODE_SUCCESS;
        }

        $io->out(sprintf('<info>Found %d attributes:</info>', count($attributes)));
        $io->out('');

        $tableData = [];
        foreach ($attributes as $attr) {
            $tableData[] = [
                $attr->attributeName,
                $attr->className,
                $attr->pluginName ?? '-',
                $attr->target->type->value,
                $attr->target->targetName,
            ];
        }

        $io->helper('Table')->output([
            ['Attribute', 'Class', 'Plugin', 'Type', 'Target'],
            ...$tableData,
        ]);

        return static::CODE_SUCCESS;
    }

    /**
     * Get filtered attributes based on command arguments.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function getFilteredAttributes(Arguments $args): array
    {
        $attributeFilter = $args->getOption('attribute');
        $classFilter = $args->getOption('class');
        $typeFilter = $args->getOption('type');

        if ($attributeFilter !== null) {
            return $this->registry->findByAttribute((string)$attributeFilter);
        }

        if ($classFilter !== null) {
            return $this->registry->findByClass((string)$classFilter);
        }

        if ($typeFilter !== null) {
            $type = AttributeTargetType::tryFrom((string)$typeFilter);
            if ($type === null) {
                return [];
            }

            return $this->registry->findByTargetType($type);
        }

        return $this->registry->discover()->toList();
    }
}
