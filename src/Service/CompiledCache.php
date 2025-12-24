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
use UnitEnum;

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
     * @param bool $validateFiles Whether to validate file hashes on cache retrieval (development mode)
     */
    public function __construct(
        string $cachePath,
        private readonly bool $enabled = true,
        private readonly bool $validateFiles = false,
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
     * Check if file validation is enabled.
     *
     * @return bool Whether file validation is enabled
     */
    public function isValidationEnabled(): bool
    {
        return $this->validateFiles;
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

        // Check in-memory cache first (but skip if validation is enabled)
        if (!$this->validateFiles && isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }

        $filePath = $this->getCacheFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $data = require $filePath;

            if (is_array($data)) {
                // Validate file hashes if enabled
                if ($this->validateFiles && $data !== []) {
                    $validated = $this->validateCachedData($data);
                    if ($validated === null) {
                        // Validation failed, cache is stale
                        return null;
                    }

                    $data = $validated;
                }

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

        // Invalidate OPcache before deleting
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($filePath, true); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
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
            // Invalidate OPcache before deleting
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            }

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
            "%s    fileHash: %s,\n" .
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
            $this->exportString($attr->fileHash),
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
        $innerIndent = str_repeat('    ', $level + 1);

        return sprintf(
            "new \\AttributeRegistry\\ValueObject\\AttributeTarget(\n" .
            '%stype: ' . AttributeTargetType::class . '::%s,' . "\n" .
            "%stargetName: %s,\n" .
            "%sparentClass: %s,\n" .
            '%s)',
            $innerIndent,
            $target->type->name,
            $innerIndent,
            $this->exportString($target->targetName),
            $innerIndent,
            $this->exportValue($target->parentClass, $level),
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
     * @param int $level Indentation level for nested structures
     * @return string Exported code
     */
    private function exportValue(mixed $value, int $level = 0): string
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

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value)) {
            // Handle special float values
            if (is_infinite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }

            if (is_nan($value)) {
                return 'NAN';
            }

            return var_export($value, true);
        }

        if (is_array($value)) {
            return $this->exportArray($value, $level);
        }

        if (is_object($value)) {
            // Enums are natively supported by var_export (PHP 8.1+)
            if ($value instanceof UnitEnum) {
                return var_export($value, true);
            }

            // For other objects, only allow those that can be reconstructed via __set_state
            if (!method_exists($value, '__set_state')) {
                throw new RuntimeException(
                    sprintf(
                        'Unsupported object type for export: %s must implement __set_state().',
                        get_debug_type($value),
                    ),
                );
            }

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
                    $this->exportValue($key, $level + 1),
                    $this->exportValue($value, $level + 1),
                );
            } else {
                $items[] = $indent . $this->exportValue($value, $level + 1);
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

            // Set permissions - log warning if it fails but continue
            if (!chmod($tempFile, 0644)) {
                Log::warning('Failed to chmod cache file: ' . $tempFile);
            }

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
        // Sanitize key for filesystem (for readability)
        $safeKey = preg_replace('/[^a-z0-9_-]/i', '_', $key);
        // Append a hash of the original key to avoid collisions between different keys
        $hash = hash('xxh3', $key);

        return $this->cachePath . $safeKey . '_' . $hash . '.php';
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

    /**
     * Validate cached data by checking file hashes.
     *
     * Returns null if any files have changed (cache is stale).
     * Returns the filtered array if all files are valid or have empty hashes (backward compatibility).
     *
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $data Cached attribute data
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>|null Validated data or null if stale
     */
    private function validateCachedData(array $data): ?array
    {
        // Cache file hashes to avoid redundant reads when multiple attributes come from the same file
        $fileHashCache = [];

        foreach ($data as $attr) {
            // Skip validation for entries without hash (backward compatibility)
            if ($attr->fileHash === '') {
                continue;
            }

            // Check if file still exists
            if (!file_exists($attr->filePath)) {
                return null;
            }

            // Get hash from cache or compute it
            if (!isset($fileHashCache[$attr->filePath])) {
                $currentHash = hash_file('xxh3', $attr->filePath);
                if ($currentHash === false) {
                    Log::warning(sprintf(
                        'Failed to compute hash for file "%s" while validating cached data.',
                        $attr->filePath,
                    ));

                    return null;
                }

                $fileHashCache[$attr->filePath] = $currentHash;
            }

            if ($fileHashCache[$attr->filePath] !== $attr->fileHash) {
                // File has changed, cache is stale
                return null;
            }
        }

        return $data;
    }
}
