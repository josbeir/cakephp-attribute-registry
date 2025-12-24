<?php
declare(strict_types=1);

namespace AttributeRegistry\Utility;

/**
 * Utility for merging configuration arrays with smart semantics.
 *
 * Handles merging of nested configuration arrays where:
 * - Associative arrays are merged recursively
 * - Sequential (list) arrays are replaced, not merged
 * - User/override values always take precedence
 */
class ConfigMerger
{
    /**
     * Merge default configuration with user/override configuration.
     *
     * User values always take precedence over defaults. For sequential arrays
     * (numeric indexed arrays), user values completely replace defaults rather
     * than being appended. For associative arrays, values are merged recursively.
     *
     * Example:
     * ```php
     * $defaults = [
     *     'cache' => ['enabled' => true, 'path' => '/default'],
     *     'paths' => ['src/**', 'lib/**']
     * ];
     * $user = [
     *     'cache' => ['enabled' => false],
     *     'paths' => ['custom/**']
     * ];
     * $result = ConfigMerger::merge($defaults, $user);
     * // Result: [
     * //     'cache' => ['enabled' => false, 'path' => '/default'],
     * //     'paths' => ['custom/**']  // Replaced, not appended
     * // ]
     * ```
     *
     * @param array<string, mixed> $defaults Default configuration values
     * @param array<string, mixed> $overrides User/override configuration values
     * @return array<string, mixed> Merged configuration with overrides taking precedence
     */
    public static function merge(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                // Sequential arrays (lists) replace entirely
                // Associative arrays merge recursively
                $defaults[$key] = array_is_list($value)
                    ? $value
                    : static::merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
