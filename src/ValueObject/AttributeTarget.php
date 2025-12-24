<?php
declare(strict_types=1);

namespace AttributeRegistry\ValueObject;

use AttributeRegistry\Enum\AttributeTargetType;

readonly class AttributeTarget
{
    /**
     * Constructor for AttributeTarget.
     *
     * @param \AttributeRegistry\Enum\AttributeTargetType $type Target type
     * @param string $targetName Target name
     * @param string|null $parentClass Parent class name if applicable
     */
    public function __construct(
        public AttributeTargetType $type,
        public string $targetName,
        public ?string $parentClass = null,
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
            'type' => $this->type->value,
            'targetName' => $this->targetName,
            'parentClass' => $this->parentClass,
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
            type: AttributeTargetType::from((string)$data['type']),
            targetName: (string)$data['targetName'],
            parentClass: isset($data['parentClass']) ? (string)$data['parentClass'] : null,
        );
    }

    /**
     * Restore object state for var_export() support.
     *
     * @param array<string, mixed> $data State data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            type: $data['type'],
            targetName: $data['targetName'],
            parentClass: $data['parentClass'] ?? null,
        );
    }
}
