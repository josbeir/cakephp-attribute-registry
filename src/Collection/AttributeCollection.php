<?php
declare(strict_types=1);

namespace AttributeRegistry\Collection;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;

/**
 * A specialized collection for AttributeInfo objects with fluent filtering methods.
 *
 * Extends CakePHP's Collection to provide domain-specific query methods while
 * retaining all standard collection operations (map, filter, groupBy, etc.).
 *
 * @template-extends \Cake\Collection\Collection<int, \AttributeRegistry\ValueObject\AttributeInfo>
 */
class AttributeCollection extends Collection
{
    /**
     * Create a new AttributeCollection instance.
     *
     * @param mixed ...$args Constructor arguments
     * @return \Cake\Collection\CollectionInterface<int, \AttributeRegistry\ValueObject\AttributeInfo>
     */
    protected function newCollection(mixed ...$args): CollectionInterface
    {
        return new self(...$args);
    }

    /**
     * Filter elements using a callback.
     *
     * @param callable|null $callback Callback to filter elements
     */
    public function filter(?callable $callback = null): self
    {
        return new self(parent::filter($callback));
    }

    /**
     * Filter by attribute class name(s).
     *
     * Multiple names use OR logic (matches any of the provided names).
     *
     * @param string ...$names Attribute FQCNs to match
     */
    public function attribute(string ...$names): self
    {
        if ($names === []) {
            return $this;
        }

        return $this->filter(
            fn(AttributeInfo $attr): bool => in_array($attr->attributeName, $names, true),
        );
    }

    /**
     * Filter by class namespace pattern(s).
     *
     * Supports exact match or wildcard suffix (e.g., 'App\Controller\*').
     * Multiple patterns use OR logic.
     *
     * @param string ...$patterns Namespace patterns to match
     */
    public function namespace(string ...$patterns): self
    {
        if ($patterns === []) {
            return $this;
        }

        return $this->filter(
            fn(AttributeInfo $attr): bool => $this->matchesNamespace($attr->className, $patterns),
        );
    }

    /**
     * Filter by attribute target type(s).
     *
     * Multiple types use OR logic.
     *
     * @param \AttributeRegistry\Enum\AttributeTargetType ...$types Target types to match
     */
    public function targetType(AttributeTargetType ...$types): self
    {
        if ($types === []) {
            return $this;
        }

        return $this->filter(
            fn(AttributeInfo $attr): bool => in_array($attr->target->type, $types, true),
        );
    }

    /**
     * Filter by class name(s).
     *
     * Multiple names use OR logic.
     *
     * @param string ...$names Class FQCNs to match
     */
    public function className(string ...$names): self
    {
        if ($names === []) {
            return $this;
        }

        return $this->filter(
            fn(AttributeInfo $attr): bool => in_array($attr->className, $names, true),
        );
    }

    /**
     * Filter by partial match on attribute class name.
     *
     * @param string $search Partial string to match in attribute name
     */
    public function attributeContains(string $search): self
    {
        return $this->filter(
            fn(AttributeInfo $attr): bool => str_contains($attr->attributeName, $search),
        );
    }

    /**
     * Filter by partial match on class name.
     *
     * @param string $search Partial string to match in class name
     */
    public function classNameContains(string $search): self
    {
        return $this->filter(
            fn(AttributeInfo $attr): bool => str_contains($attr->className, $search),
        );
    }

    /**
     * Check if a class name matches any of the namespace patterns.
     *
     * @param string $className The class name to check
     * @param array<string> $patterns Namespace patterns to match against
     */
    private function matchesNamespace(string $className, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $className) {
                return true;
            }

            if (str_ends_with($pattern, '\\*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($className, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
