<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\ValueObject\AttributeCacheValidationResult;

/**
 * Validates attribute cache integrity.
 */
class AttributeCacheValidator
{
    /**
     * Constructor.
     *
     * @param \AttributeRegistry\AttributeRegistry $registry Attribute registry instance
     */
    public function __construct(private AttributeRegistry $registry)
    {
    }

    /**
     * Validate the attribute cache.
     *
     * Checks that all cached attributes reference existing files
     * and that file hashes match (if present).
     *
     * @return \AttributeRegistry\ValueObject\AttributeCacheValidationResult
     */
    public function validate(): AttributeCacheValidationResult
    {
        $discovered = $this->registry->discover();
        $attributes = [];

        // Collect all attributes
        foreach ($discovered as $attribute) {
            $attributes[] = $attribute;
        }

        // If no attributes, return success with 0 attributes
        if ($attributes === []) {
            return AttributeCacheValidationResult::success(0, 0);
        }

        $errors = [];
        $filesChecked = [];

        foreach ($attributes as $attribute) {
            $filePath = $attribute->filePath;
            $fileHash = $attribute->fileHash;

            // Track unique files
            $filesChecked[$filePath] = true;

            // Check file existence
            if (!file_exists($filePath)) {
                $errors[] = 'File not found: ' . $filePath;
                continue;
            }

            // Check hash if present (backward compatibility - skip if empty)
            if (!empty($fileHash)) {
                $actualHash = hash_file('xxh3', $filePath);
                if ($actualHash !== $fileHash) {
                    $errors[] = 'Hash mismatch for file: ' . $filePath;
                }
            }
        }

        if ($errors !== []) {
            return AttributeCacheValidationResult::failure(
                $errors,
                count($attributes),
                count($filesChecked),
            );
        }

        return AttributeCacheValidationResult::success(
            count($attributes),
            count($filesChecked),
        );
    }
}
