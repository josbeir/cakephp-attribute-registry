<?php
declare(strict_types=1);

namespace AttributeRegistry\ValueObject;

use InvalidArgumentException;
use RuntimeException;

readonly class AttributeInfo
{
    /**
     * Constructor for AttributeInfo.
     *
     * @param string $className Class name containing the attribute
     * @param string $attributeName Attribute class name
     * @param array<string, mixed> $arguments Attribute arguments
     * @param string $filePath File path where attribute was found
     * @param int $lineNumber Line number where attribute was found
     * @param \AttributeRegistry\ValueObject\AttributeTarget $target Target information
     * @param int $fileTime File modification time (Unix timestamp) for cache validation
     * @param string|null $pluginName Plugin name or null for App namespace
     */
    public function __construct(
        public string $className,
        public string $attributeName,
        public array $arguments,
        public string $filePath,
        public int $lineNumber,
        public AttributeTarget $target,
        public int $fileTime = 0,
        public ?string $pluginName = null,
    ) {
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'className' => $this->className,
            'attributeName' => $this->attributeName,
            'arguments' => $this->arguments,
            'filePath' => $this->filePath,
            'lineNumber' => $this->lineNumber,
            'target' => $this->target->toArray(),
            'fileTime' => $this->fileTime,
            'pluginName' => $this->pluginName,
        ];
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data Data array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            className: (string)$data['className'],
            attributeName: (string)$data['attributeName'],
            arguments: (array)$data['arguments'],
            filePath: (string)$data['filePath'],
            lineNumber: (int)$data['lineNumber'],
            target: AttributeTarget::fromArray((array)$data['target']),
            fileTime: (int)($data['fileTime'] ?? 0),
            pluginName: $data['pluginName'] ?? null,
        );
    }

    /**
     * Restore object state for var_export() support.
     *
     * @param array<string, mixed> $data State data
     */
    public static function __set_state(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * Instantiate the actual attribute object.
     *
     * Returns the attribute instance with its arguments applied,
     * allowing access to attribute properties and methods.
     *
     * @template T of object
     * @param class-string<T>|null $expectedClass Optional expected class for type safety
     * @return T|object The instantiated attribute
     * @throws \RuntimeException If the attribute class does not exist
     * @throws \InvalidArgumentException If attribute doesn't match expected class
     */
    public function getInstance(?string $expectedClass = null): object
    {
        if (!class_exists($this->attributeName)) {
            throw new RuntimeException(sprintf(
                'Attribute class "%s" does not exist',
                $this->attributeName,
            ));
        }

        $instance = new ($this->attributeName)(...$this->arguments);

        if ($expectedClass !== null && !$instance instanceof $expectedClass) {
            throw new InvalidArgumentException(sprintf(
                'Attribute "%s" is not an instance of "%s"',
                $this->attributeName,
                $expectedClass,
            ));
        }

        return $instance;
    }

    /**
     * Check if the attribute is an instance of a given class.
     *
     * @param class-string $className Class name to check against
     */
    public function isInstanceOf(string $className): bool
    {
        return is_a($this->attributeName, $className, true);
    }
}
