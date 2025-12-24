<?php
declare(strict_types=1);

namespace AttributeRegistry\Utility;

/**
 * Utility class for normalizing file paths across platforms.
 */
class PathNormalizer
{
    /**
     * Normalize path separators to the platform's DIRECTORY_SEPARATOR.
     *
     * Converts both forward slashes and backslashes to the platform's
     * directory separator for consistent path comparison.
     *
     * @param string $path Path to normalize
     * @return string Normalized path with platform-specific separators
     */
    public static function normalize(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Normalize path to use forward slashes.
     *
     * Useful for glob patterns which work better with forward slashes
     * on all platforms including Windows.
     *
     * @param string $path Path to normalize
     * @return string Path with forward slashes
     */
    public static function toUnixStyle(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Get the canonical absolute path.
     *
     * Resolves symbolic links and relative references (. and ..).
     * Returns false if the path doesn't exist.
     *
     * @param string $path Path to resolve
     * @return string|false Canonical path or false on failure
     */
    public static function canonicalize(string $path): string|false
    {
        return realpath($path);
    }
}
