<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

class AttributeParser
{
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
            // Include the file to make classes available for reflection
            require_once $filePath;

            // Get all declared classes
            $declaredClasses = get_declared_classes();

            // Filter classes that are likely from this file
            $fileClasses = $this->getClassesFromFile($filePath, $declaredClasses);

            foreach ($fileClasses as $className) {
                try {
                    /** @var class-string $className */
                    $reflection = new ReflectionClass($className);

                    // Skip classes not from this file
                    if ($reflection->getFileName() !== $filePath) {
                        continue;
                    }

                    $attributes = array_merge(
                        $attributes,
                        $this->extractClassAttributes($reflection, $filePath, $fileModTime),
                    );
                    $attributes = array_merge(
                        $attributes,
                        $this->extractMethodAttributes($reflection, $filePath, $fileModTime),
                    );
                    $attributes = array_merge(
                        $attributes,
                        $this->extractPropertyAttributes($reflection, $filePath, $fileModTime),
                    );
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
            $attributeClass = new ReflectionClass($attribute->getName());
            $constructor = $attributeClass->getConstructor();

            if ($constructor === null) {
                return $rawArgs;
            }

            $parameters = $constructor->getParameters();
            $namedArgs = [];

            // Map positional arguments to parameter names
            foreach ($rawArgs as $index => $value) {
                if (isset($parameters[$index])) {
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
     * @param array<string> $allClasses
     * @return array<string>
     */
    private function getClassesFromFile(string $filePath, array $allClasses): array
    {
        // Get file content to extract namespace and class names
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        // Extract namespace
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        // Extract class names
        preg_match_all('/(?:class|interface|trait)\s+(\w+)/', $content, $classMatches);
        $classNames = $classMatches[1];

        $fileClasses = [];
        foreach ($classNames as $className) {
            $fullClassName = $namespace !== '' && $namespace !== '0' ? $namespace . '\\' . $className : $className;
            if (in_array($fullClassName, $allClasses)) {
                $fileClasses[] = $fullClassName;
            }
        }

        return $fileClasses;
    }
}
