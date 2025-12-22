<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
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
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception('File not found: ' . $filePath);
        }

        $attributes = [];
        $fileModTime = filemtime($filePath);
        if ($fileModTime === false) {
            throw new Exception('Could not get modification time for file: ' . $filePath);
        }

        try {
            // Get classes from this file using diff or reflection
            $fileClasses = $this->getClassesFromFile($filePath);

            foreach ($fileClasses as $className) {
                try {
                    /** @var class-string $className */
                    $reflection = new ReflectionClass($className);

                    // Skip classes not from this file
                    if ($reflection->getFileName() !== $filePath) {
                        continue;
                    }

                    $attributes = [
                        ...$attributes,
                        ...$this->extractClassAttributes($reflection, $filePath, $fileModTime),
                        ...$this->extractMethodAttributes($reflection, $filePath, $fileModTime),
                        ...$this->extractPropertyAttributes($reflection, $filePath, $fileModTime),
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
     * @param \ReflectionClass<object> $class Reflection class
     * @param string $filePath File path
     * @param int $fileModTime File modification time
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractClassAttributes(ReflectionClass $class, string $filePath, int $fileModTime): array
    {
        $attributes = [];

        foreach ($class->getAttributes() as $attribute) {
            $startLine = $class->getStartLine();
            $attributes[] = $this->createAttributeInfo(
                $attribute,
                $class->getName(),
                $filePath,
                $startLine === false ? 0 : $startLine,
                $fileModTime,
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
     * @param int $fileModTime File modification time
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractMethodAttributes(ReflectionClass $class, string $filePath, int $fileModTime): array
    {
        $attributes = [];

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $startLine = $method->getStartLine();
                $attributes[] = $this->createAttributeInfo(
                    $attribute,
                    $class->getName(),
                    $filePath,
                    $startLine === false ? 0 : $startLine,
                    $fileModTime,
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
     * @param int $fileModTime File modification time
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function extractPropertyAttributes(ReflectionClass $class, string $filePath, int $fileModTime): array
    {
        $attributes = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $attributes[] = $this->createAttributeInfo(
                    $attribute,
                    $class->getName(),
                    $filePath,
                    0, // Properties don't have reliable line numbers
                    $fileModTime,
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
     * Create an AttributeInfo instance from reflection data.
     *
     * @param \ReflectionAttribute<object> $attribute Reflection attribute
     * @param string $className Class name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     * @param int $fileModTime File modification time
     * @param \AttributeRegistry\ValueObject\AttributeTarget $target Target information
     * @return \AttributeRegistry\ValueObject\AttributeInfo Created AttributeInfo instance
     */
    private function createAttributeInfo(
        ReflectionAttribute $attribute,
        string $className,
        string $filePath,
        int $lineNumber,
        int $fileModTime,
        AttributeTarget $target,
    ): AttributeInfo {
        return new AttributeInfo(
            className: $className,
            attributeName: $attribute->getName(),
            arguments: $this->extractAttributeArguments($attribute),
            filePath: $filePath,
            lineNumber: $lineNumber,
            target: $target,
            fileModTime: $fileModTime,
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
     * @param string $filePath File path to get classes from
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
        $fileClasses = [];
        foreach (get_declared_classes() as $className) {
            try {
                $reflection = new ReflectionClass($className);
                if ($reflection->getFileName() === $filePath) {
                    $fileClasses[] = $className;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $fileClasses;
    }
}
