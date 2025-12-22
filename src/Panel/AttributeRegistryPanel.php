<?php
declare(strict_types=1);

namespace AttributeRegistry\Panel;

use AttributeRegistry\AttributeRegistry;
use Cake\Core\Configure;
use DebugKit\DebugPanel;

/**
 * DebugKit panel for viewing discovered PHP attributes.
 *
 * Displays all attributes discovered by the AttributeRegistry plugin
 * with grouping by attribute class and target file.
 */
class AttributeRegistryPanel extends DebugPanel
{
    /**
     * Plugin name for template resolution.
     */
    public string $plugin = 'AttributeRegistry';

    /**
     * Get data for panel display.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $registry = AttributeRegistry::getInstance();
        $attributes = $registry->discover();

        return [
            'attributes' => $attributes,
            'count' => count($attributes),
            'groupedByAttribute' => $this->groupByAttribute($attributes),
            'groupedByTarget' => $this->groupByTarget($attributes),
            'config' => (array)Configure::read('AttributeRegistry'),
        ];
    }

    /**
     * Get summary text shown in the toolbar.
     */
    public function summary(): string
    {
        return (string)count(AttributeRegistry::getInstance()->discover());
    }

    /**
     * Get panel title.
     */
    public function title(): string
    {
        return 'Attribute Registry';
    }

    /**
     * Group attributes by their attribute class name.
     *
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $attributes Attributes to group
     * @return array<string, array<\AttributeRegistry\ValueObject\AttributeInfo>>
     */
    private function groupByAttribute(array $attributes): array
    {
        $grouped = [];
        foreach ($attributes as $attribute) {
            $name = $attribute->attributeName;
            $grouped[$name] ??= [];
            $grouped[$name][] = $attribute;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Group attributes by their target file.
     *
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $attributes Attributes to group
     * @return array<string, array<\AttributeRegistry\ValueObject\AttributeInfo>>
     */
    private function groupByTarget(array $attributes): array
    {
        $grouped = [];
        foreach ($attributes as $attribute) {
            $file = $attribute->filePath;
            $grouped[$file] ??= [];
            $grouped[$file][] = $attribute;
        }

        ksort($grouped);

        return $grouped;
    }
}
