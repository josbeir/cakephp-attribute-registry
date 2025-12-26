<?php
declare(strict_types=1);

namespace AttributeRegistry\Utility;

/**
 * File hash utility for consistent file integrity validation.
 *
 * Provides centralized file hashing functionality using xxh3 algorithm for
 * detecting when source files have changed and caches need invalidation.
 *
 * xxh3 is chosen for its exceptional speed and quality distribution,
 * making it ideal for file validation.
 */
class HashUtility
{
    /**
     * Hash algorithm used throughout the plugin.
     *
     * xxh3 (xxHash 64-bit variant) provides:
     * - Exceptional performance (faster than md5/sha1)
     * - Good hash distribution (low collision rate)
     * - Native PHP support (PHP 8.1+)
     */
    private const ALGORITHM = 'xxh3';

    /**
     * Hash a file's contents.
     *
     * Used for file integrity validation to detect when source files
     * have changed and caches need invalidation.
     *
     * @param string $filePath Path to file
     * @return string|false Hash value or false on failure
     */
    public static function hashFile(string $filePath): string|false
    {
        return @hash_file(self::ALGORITHM, $filePath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }
}
