<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Utility\HashUtility;
use AttributeRegistry\Utility\PathNormalizer;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

class AttributeParser
{
    /**
     * Cache for attribute constructor reflections.
     *
     * @var array<string, \ReflectionMethod|null>
     */
    private static array $constructorCache = [];

    /**
     * Constructor for AttributeParser.
     *
     * @param array<string> $excludeAttributes List of attribute FQCNs to exclude (supports wildcards)
     * @param \AttributeRegistry\Service\PluginLocator|null $pluginLocator Plugin locator for plugin detection
     */
    public function __construct(
        private array $excludeAttributes = [],
        private ?PluginLocator $pluginLocator = null,
    ) {
    }

    /**
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception('File not found: ' . $filePath);
        }

        // Normalize the file path to use realpath for consistent comparison
        $realFilePath = realpath($filePath);
        if ($realFilePath === false) {
            throw new Exception('Could not resolve real path for: ' . $filePath);
        }

        $attributes = [];

        // Generate file content hash for cache validation
        $fileHash = HashUtility::hashFile($realFilePath);
        if ($fileHash === false) {
            throw new RuntimeException(sprintf(
                'Failed to compute hash for file "%s"',
                $realFilePath,
            ));
        }

        try {
            // Get classes from this file using diff or reflection
            $fileClasses = $this->getClassesFromFile($realFilePath);

            foreach ($fileClasses as $className) {
                try {
                    /** @var class-string $className */
                    $reflection = new ReflectionClass($className);

                    // Skip classes not from this file (normalize both paths for comparison)
                    $reflectionFile = $reflection->getFileName();
                    if ($reflectionFile === false || realpath($reflectionFile) !== $realFilePath) {
                        continue;
                    }

                    $attributes = [
                        ...$attributes,
                        ...$this->extractClassAttributes($reflection, $realFilePath, $fileHash),
                        ...$this->extractMethodAttributes($reflection, $realFilePath, $fileHash),
                        ...$this->extractPropertyAttributes($reflection, $realFilePath, $fileHash),
                        ...$this->extractParameterAttributes($reflection, $realFilePath, $fileHash),
                        ...$this->extractConstantAttributes($reflection, $realFilePath, $fileHash),
                    ];
                } catch (Throwable $e) {
                    // Skip classes that can't be reflected
                    continue;
                }
            }
        } catch (Throwable $throwable) {
            // If we can't parse the file, return empty array
            return [];
        }

        return $attributes;
    }

    /**
     * Check if an attribute should be excluded from results.
     *
     * @param \ReflectionAttribute<object> $attribute Reflection attribute to check
     * @return bool True if attribute should be excluded
     */
    private function isAttributeExcluded(ReflectionAttribute $attribute): bool
    {
        if ($this->excludeAttributes === []) {
            return false;
        }

        $fqcn = $attribute->getName();

        foreach ($this->excludeAttributes as $pattern) {
            // Exact match
            if ($pattern === $fqcn) {
                return true;
            }

            // Namespace wildcard: "App\Internal\*" matches "App\Internal\Foo"
            if (str_ends_with($pattern, '\*')) {
                $prefix = substr($pattern, 0, -1); // Remove the "*", keep the "\"
                if (str_starts_with($fqcn, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param string $fileHash File content hash
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractClassAttributes(
        ReflectionClass $class,
        string $filePath,
        string $fileHash,
    ): array {
        $attributes = [];

        foreach ($class->getAttributes() as $attribute) {
            if ($this->isAttributeExcluded($attribute)) {
                continue;
            }

            $startLine = $class->getStartLine();
            $attributes[] = $this->createAttributeInfo(
                $attribute,
                $class->getName(),
                $filePath,
                $startLine === false ? 0 : $startLine,
                $fileHash,
                new AttributeTarget(
                    AttributeTargetType::CLASS_TYPE,
                    $class->getShortName(),
                ),
            );
        }

        return $attributes;
    }

    /**
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param string $fileHash File content hash
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractMethodAttributes(
        ReflectionClass $class,
        string $filePath,
        string $fileHash,
    ): array {
        $attributes = [];

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                if ($this->isAttributeExcluded($attribute)) {
                    continue;
                }

                $startLine = $method->getStartLine();
                $attributes[] = $this->createAttributeInfo(
                    $attribute,
                    $class->getName(),
                    $filePath,
                    $startLine === false ? 0 : $startLine,
                    $fileHash,
                    new AttributeTarget(
                        AttributeTargetType::METHOD,
                        $method->getName(),
                        $class->getShortName(),
                    ),
                );
            }
        }

        return $attributes;
    }

    /**
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param string $fileHash File content hash
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractPropertyAttributes(
        ReflectionClass $class,
        string $filePath,
        string $fileHash,
    ): array {
        $attributes = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if ($this->isAttributeExcluded($attribute)) {
                    continue;
                }

                $attributes[] = $this->createAttributeInfo(
                    $attribute,
                    $class->getName(),
                    $filePath,
                    0, // Properties don't have reliable line numbers
                    $fileHash,
                    new AttributeTarget(
                        AttributeTargetType::PROPERTY,
                        $property->getName(),
                        $class->getShortName(),
                    ),
                );
            }
        }

        return $attributes;
    }

    /**
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param string $fileHash File content hash
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractParameterAttributes(
        ReflectionClass $class,
        string $filePath,
        string $fileHash,
    ): array {
        $attributes = [];

        foreach ($class->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                foreach ($parameter->getAttributes() as $attribute) {
                    if ($this->isAttributeExcluded($attribute)) {
                        continue;
                    }

                    $startLine = $method->getStartLine();
                    $attributes[] = $this->createAttributeInfo(
                        $attribute,
                        $class->getName(),
                        $filePath,
                        $startLine === false ? 0 : $startLine,
                        $fileHash,
                        new AttributeTarget(
                            AttributeTargetType::PARAMETER,
                            $parameter->getName(),
                            $method->getName(),
                        ),
                    );
                }
            }
        }

        return $attributes;
    }

    /**
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param string $fileHash File content hash
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractConstantAttributes(
        ReflectionClass $class,
        string $filePath,
        string $fileHash,
    ): array {
        $attributes = [];

        foreach ($class->getReflectionConstants() as $constant) {
            foreach ($constant->getAttributes() as $attribute) {
                if ($this->isAttributeExcluded($attribute)) {
                    continue;
                }

                $attributes[] = $this->createAttributeInfo(
                    $attribute,
                    $class->getName(),
                    $filePath,
                    0, // Constants don't have reliable line numbers
                    $fileHash,
                    new AttributeTarget(
                        AttributeTargetType::CONSTANT,
                        $constant->getName(),
                        $class->getShortName(),
                    ),
                );
            }
        }

        return $attributes;
    }

    /**
     * Create an AttributeInfo instance from reflection data.
     *
     * @param \ReflectionAttribute<object> $attribute Reflection attribute
     * @param string $className Class name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     * @param string $fileHash File content hash
     * @param \AttributeRegistry\ValueObject\AttributeTarget $target Target information
     * @return \AttributeRegistry\ValueObject\AttributeInfo Created AttributeInfo instance
     */
    private function createAttributeInfo(
        ReflectionAttribute $attribute,
        string $className,
        string $filePath,
        int $lineNumber,
        string $fileHash,
        AttributeTarget $target,
    ): AttributeInfo {
        $pluginName = $this->pluginLocator?->getPluginNameFromPath($filePath);

        return new AttributeInfo(
            className: $className,
            attributeName: $attribute->getName(),
            arguments: $this->extractAttributeArguments($attribute),
            filePath: $filePath,
            lineNumber: $lineNumber,
            target: $target,
            fileHash: $fileHash,
            pluginName: $pluginName,
        );
    }

    /**
     * Extract named arguments from a reflection attribute.
     *
     * @param \ReflectionAttribute<object> $attribute Reflection attribute
     * @return array<string, mixed> Named arguments array
     */
    private function extractAttributeArguments(ReflectionAttribute $attribute): array
    {
        try {
            $rawArgs = $attribute->getArguments();
            $constructor = $this->getAttributeConstructor($attribute->getName());

            if (!$constructor instanceof ReflectionMethod) {
                return $rawArgs;
            }

            $parameters = $constructor->getParameters();
            $namedArgs = [];

            // Map arguments to parameter names
            foreach ($rawArgs as $index => $value) {
                if (is_string($index)) {
                    // Already a named argument
                    $namedArgs[$index] = $value;
                } elseif (isset($parameters[$index])) {
                    // Positional argument - map to parameter name
                    $namedArgs[$parameters[$index]->getName()] = $value;
                }
            }

            return $namedArgs;
        } catch (Throwable $throwable) {
            // Fallback to raw arguments
            return $attribute->getArguments();
        }
    }

    /**
     * Get the constructor for an attribute class (cached).
     *
     * @param class-string $attributeName Attribute class name
     * @return \ReflectionMethod|null Constructor or null if none exists
     */
    private function getAttributeConstructor(string $attributeName): ?ReflectionMethod
    {
        if (!array_key_exists($attributeName, self::$constructorCache)) {
            /** @var class-string $attributeName */
            $attributeClass = new ReflectionClass($attributeName);
            self::$constructorCache[$attributeName] = $attributeClass->getConstructor();
        }

        return self::$constructorCache[$attributeName];
    }

    /**
     * Get classes defined in a file.
     *
     * Uses class diffing when file is not yet loaded, falls back to
     * iterating declared classes when file was already included.
     *
     * @param string $filePath File path to get classes from (should be normalized with realpath)
     * @return array<string> Class names from the file
     */
    private function getClassesFromFile(string $filePath): array
    {
        $includedFiles = get_included_files();
        $alreadyLoaded = in_array($filePath, $includedFiles, true);

        if (!$alreadyLoaded) {
            // Fast path: diff classes before/after require
            $classesBefore = get_declared_classes();
            require_once $filePath;

            return array_values(array_diff(get_declared_classes(), $classesBefore));
        }

        // Fallback: file already loaded, find classes by checking their file
        // Normalize target path once for comparison
        $normalizedFilePath = PathNormalizer::normalize($filePath);

        $fileClasses = [];
        foreach (get_declared_classes() as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $reflectionFile = $reflection->getFileName();
                // Normalize reflection file path for cross-platform comparison
                if ($reflectionFile !== false) {
                    $normalizedReflectionFile = PathNormalizer::normalize($reflectionFile);
                    if ($normalizedReflectionFile === $normalizedFilePath) {
                        $fileClasses[] = $className;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $fileClasses;
    }
}
