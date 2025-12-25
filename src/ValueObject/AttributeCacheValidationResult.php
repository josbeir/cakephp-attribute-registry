<?php
declare(strict_types=1);

namespace AttributeRegistry\ValueObject;

/**
 * Result of attribute cache validation.
 */
readonly class AttributeCacheValidationResult
{
    /**
     * @param bool $valid Whether the cache is valid
     * @param array<string> $errors List of validation errors
     * @param int $totalAttributes Total number of attributes validated
     * @param int $totalFiles Total number of unique files checked
     * @param array<string> $warnings List of validation warnings
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public int $totalAttributes,
        public int $totalFiles,
        public array $warnings = [],
    ) {
    }

    /**
     * Create a success result.
     *
     * @param int $totalAttributes Total attributes
     * @param int $totalFiles Total files
     */
    public static function success(int $totalAttributes, int $totalFiles): self
    {
        return new self(true, [], $totalAttributes, $totalFiles);
    }

    /**
     * Create a failure result.
     *
     * @param array<string> $errors Validation errors
     * @param int $totalAttributes Total attributes
     * @param int $totalFiles Total files
     */
    public static function failure(array $errors, int $totalAttributes, int $totalFiles): self
    {
        return new self(false, $errors, $totalAttributes, $totalFiles);
    }

    /**
     * Create a result for cache not found.
     */
    public static function notCached(): self
    {
        return new self(false, ['Cache not found or empty'], 0, 0);
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
