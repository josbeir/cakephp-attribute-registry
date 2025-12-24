<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Cake\Log\Log;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Compiled cache service for zero-cost attribute caching.
 *
 * Generates executable PHP files that directly instantiate AttributeInfo objects,
 * leveraging OPcache for maximum performance with zero hydration overhead.
 */
class CompiledCache
{
    private readonly string $cachePath;

    /**
     * @var array<string, array<\AttributeRegistry\ValueObject\AttributeInfo>> In-memory cache
     */
    private array $memoryCache = [];

    /**
     * Constructor for CompiledCache.
     *
     * @param string $cachePath Path to store compiled cache files (will be normalized with trailing separator)
     * @param bool $enabled Whether caching is enabled
     */
    public function __construct(
        string $cachePath,
        private readonly bool $enabled = true,
    ) {
        // Ensure cachePath ends with directory separator
        if ($cachePath !== '' && !str_ends_with($cachePath, DIRECTORY_SEPARATOR)) {
            $cachePath .= DIRECTORY_SEPARATOR;
        }

        $this->cachePath = $cachePath;
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool Whether caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get cached data by key.
     *
     * @param string $key Cache key
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>|null Cached data or null if not found
     */
    public function get(string $key): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        // Check in-memory cache first
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }

        $filePath = $this->getCacheFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $data = require $filePath;

            if (is_array($data)) {
                $this->memoryCache[$key] = $data;

                return $data;
            }

            return null;
        } catch (Throwable $throwable) {
            Log::error('Failed to load compiled cache: ' . $throwable->getMessage());

            return null;
        }
    }

    /**
     * Set data in cache.
     *
     * @param string $key Cache key
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $data Data to cache
     * @return bool Success status
     */
    public function set(string $key, array $data): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            // Validate all items
            foreach ($data as $item) {
                if (!$item instanceof AttributeInfo) {
                    throw new RuntimeException('Data must contain AttributeInfo objects');
                }

                $this->validateAttributeInfo($item);
            }

            $code = $this->generateCompiledCode($data);
            $filePath = $this->getCacheFilePath($key);

            $result = $this->atomicWrite($filePath, $code);

            if ($result) {
                // Update in-memory cache
                $this->memoryCache[$key] = $data;
            }

            return $result;
        } catch (Throwable $throwable) {
            Log::error('Failed to write compiled cache: ' . $throwable->getMessage());

            return false;
        }
    }

    /**
     * Delete cached data by key.
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Clear from memory cache
        if (isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
        }

        $filePath = $this->getCacheFilePath($key);
        if (!file_exists($filePath)) {
            return true; // Nothing to delete is success
        }

        return @unlink($filePath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }

    /**
     * Clear all cached data.
     *
     * @return bool Success status
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Clear memory cache
        $this->memoryCache = [];

        if (!is_dir($this->cachePath)) {
            return true;
        }

        $files = glob($this->cachePath . '*.php');
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            @unlink($file); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        return true;
    }

    /**
     * Generate compiled PHP code from AttributeInfo array.
     *
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $attributeInfos Attributes to compile
     * @return string Generated PHP code
     */
    private function generateCompiledCode(array $attributeInfos): string
    {
        $items = array_map(
            fn(AttributeInfo $attr): string => $this->generateAttributeInfo($attr),
            $attributeInfos,
        );

        return $this->buildFileContent($items, count($attributeInfos));
    }

    /**
     * Generate code for a single AttributeInfo instance.
     *
     * @param \AttributeRegistry\ValueObject\AttributeInfo $attr Attribute to generate code for
     * @return string Generated code
     */
    private function generateAttributeInfo(AttributeInfo $attr): string
    {
        $indent = '    ';

        return sprintf(
            "%snew \\AttributeRegistry\\ValueObject\\AttributeInfo(\n" .
            "%s    className: %s,\n" .
            "%s    attributeName: %s,\n" .
            "%s    arguments: %s,\n" .
            "%s    filePath: %s,\n" .
            "%s    lineNumber: %d,\n" .
            "%s    target: %s,\n" .
            "%s    fileModTime: %d,\n" .
            '%s)',
            $indent,
            $indent,
            $this->exportString($attr->className),
            $indent,
            $this->exportString($attr->attributeName),
            $indent,
            $this->exportArray($attr->arguments, 2),
            $indent,
            $this->exportString($attr->filePath),
            $indent,
            $attr->lineNumber,
            $indent,
            $this->generateAttributeTarget($attr->target, 2),
            $indent,
            $attr->fileModTime,
            $indent,
        );
    }

    /**
     * Generate code for an AttributeTarget instance.
     *
     * @param \AttributeRegistry\ValueObject\AttributeTarget $target Target to generate code for
     * @param int $level Indentation level
     * @return string Generated code
     */
    private function generateAttributeTarget(AttributeTarget $target, int $level): string
    {
        $indent = str_repeat('    ', $level);

        return sprintf(
            "new \\AttributeRegistry\\ValueObject\\AttributeTarget(\n" .
            ('%stype: ' . AttributeTargetType::class . '::%s,
') .
            "%stargetName: %s,\n" .
            "%sparentClass: %s,\n" .
            '%s)',
            $indent,
            $target->type->name,
            $indent,
            $this->exportString($target->targetName),
            $indent,
            $this->exportValue($target->parentClass),
            $indent,
        );
    }

    /**
     * Export a string value as PHP code.
     *
     * @param string $value String to export
     * @return string Exported code
     */
    private function exportString(string $value): string
    {
        // Use var_export for safety with special characters
        return var_export($value, true);
    }

    /**
     * Export any value as PHP code.
     *
     * @param mixed $value Value to export
     * @return string Exported code
     */
    private function exportValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            return $this->exportString($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            return $this->exportArray($value, 0);
        }

        if (is_object($value)) {
            // For objects, use var_export which will create an object from __set_state
            return var_export($value, true);
        }

        throw new RuntimeException('Unsupported value type: ' . get_debug_type($value));
    }

    /**
     * Export an array as PHP code.
     *
     * @param array<mixed> $array Array to export
     * @param int $level Indentation level
     * @return string Exported code
     */
    private function exportArray(array $array, int $level): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $level + 1);
        $closeIndent = str_repeat('    ', $level);

        $isAssoc = array_keys($array) !== range(0, count($array) - 1);

        $items = [];
        foreach ($array as $key => $value) {
            if ($isAssoc) {
                $items[] = sprintf(
                    '%s%s => %s',
                    $indent,
                    $this->exportValue($key),
                    $this->exportValue($value),
                );
            } else {
                $items[] = $indent . $this->exportValue($value);
            }
        }

        return "[\n" . implode(",\n", $items) . ",\n{$closeIndent}]";
    }

    /**
     * Build complete file content with header and metadata.
     *
     * @param array<string> $items Generated attribute items
     * @param int $count Number of attributes
     * @return string Complete PHP file content
     */
    private function buildFileContent(array $items, int $count): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $itemsCode = $items === [] ? '' : "\n" . implode(",\n\n", $items) . ",\n";

        return <<<PHP
<?php
// phpcs:ignoreFile
/**
 * Pre-compiled Attribute Registry Cache
 *
 * Generated: {$timestamp}
 * Attributes: {$count}
 *
 * DO NOT EDIT THIS FILE MANUALLY
 * Regenerate with: bin/cake attribute discover
 */

declare(strict_types=1);

return [{$itemsCode}];

PHP;
    }

    /**
     * Write content to file atomically.
     *
     * @param string $filePath Target file path
     * @param string $content Content to write
     * @return bool Success status
     */
    private function atomicWrite(string $filePath, string $content): bool
    {
        $dir = dirname($filePath);

        // Ensure directory exists
        if (!is_dir($dir) && (!mkdir($dir, 0755, true) && !is_dir($dir))) {
            return false;
        }

        // Write to temporary file first
        $tempFile = $filePath . '.' . uniqid('tmp', true);

        try {
            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                return false;
            }

            // Set permissions
            chmod($tempFile, 0644);

            // Atomic rename
            if (!rename($tempFile, $filePath)) {
                @unlink($tempFile); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

                return false;
            }

            // Clear OPcache for this file
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filePath, true);
            }

            return true;
        } catch (Throwable $throwable) {
            @unlink($tempFile); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

            return false;
        }
    }

    /**
     * Get cache file path for a key.
     *
     * @param string $key Cache key
     * @return string File path
     */
    private function getCacheFilePath(string $key): string
    {
        // Sanitize key for filesystem
        $safeKey = preg_replace('/[^a-z0-9_-]/i', '_', $key);

        return $this->cachePath . $safeKey . '.php';
    }

    /**
     * Validate an AttributeInfo object for exportability.
     *
     * @param \AttributeRegistry\ValueObject\AttributeInfo $attr Attribute to validate
     * @throws \RuntimeException If attribute contains non-exportable values
     */
    private function validateAttributeInfo(AttributeInfo $attr): void
    {
        // Check arguments for non-exportable types
        $this->validateValue($attr->arguments, 'arguments');
    }

    /**
     * Validate a value for exportability.
     *
     * @param mixed $value Value to validate
     * @param string $context Context for error messages
     * @throws \RuntimeException If value is not exportable
     */
    private function validateValue(mixed $value, string $context): void
    {
        if (is_resource($value)) {
            throw new RuntimeException('Cannot export attribute with resource in ' . $context);
        }

        if ($value instanceof Closure) {
            throw new RuntimeException('Cannot export attribute with closure in ' . $context);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->validateValue($item, sprintf('%s[%s]', $context, $key));
            }
        }

        // Allow objects - var_export will handle them
    }
}
