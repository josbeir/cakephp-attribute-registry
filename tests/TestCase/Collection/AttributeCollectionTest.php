<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Collection;

use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use PHPUnit\Framework\TestCase;

class AttributeCollectionTest extends TestCase
{
    /**
     * @var array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private array $testAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testAttributes = [
            new AttributeInfo(
                className: 'App\\Controller\\UsersController',
                attributeName: 'App\\Attribute\\Route',
                arguments: ['path' => '/users', 'method' => 'GET'],
                filePath: '/app/src/Controller/UsersController.php',
                lineNumber: 10,
                target: new AttributeTarget(AttributeTargetType::CLASS_TYPE, 'UsersController'),
                fileHash: '',
            ),
            new AttributeInfo(
                className: 'App\\Controller\\UsersController',
                attributeName: 'App\\Attribute\\Route',
                arguments: ['path' => '/users/{id}', 'method' => 'GET'],
                filePath: '/app/src/Controller/UsersController.php',
                lineNumber: 20,
                target: new AttributeTarget(AttributeTargetType::METHOD, 'view', 'UsersController'),
                fileHash: '',
            ),
            new AttributeInfo(
                className: 'App\\Controller\\Api\\PostsController',
                attributeName: 'App\\Attribute\\Route',
                arguments: ['path' => '/api/posts', 'method' => 'POST'],
                filePath: '/app/src/Controller/Api/PostsController.php',
                lineNumber: 15,
                target: new AttributeTarget(AttributeTargetType::METHOD, 'add', 'PostsController'),
                fileHash: '',
            ),
            new AttributeInfo(
                className: 'App\\Model\\Entity\\User',
                attributeName: 'App\\Attribute\\Column',
                arguments: ['type' => 'string', 'length' => 255],
                filePath: '/app/src/Model/Entity/User.php',
                lineNumber: 12,
                target: new AttributeTarget(AttributeTargetType::PROPERTY, 'name', 'User'),
                fileHash: '',
            ),
            new AttributeInfo(
                className: 'App\\Service\\UserService',
                attributeName: 'App\\Attribute\\Inject',
                arguments: [],
                filePath: '/app/src/Service/UserService.php',
                lineNumber: 8,
                target: new AttributeTarget(AttributeTargetType::PARAMETER, 'repository', '__construct'),
                fileHash: '',
            ),
        ];
    }

    private function createCollection(): AttributeCollection
    {
        return new AttributeCollection($this->testAttributes);
    }

    public function testIsCollection(): void
    {
        $collection = $this->createCollection();

        $this->assertInstanceOf(CollectionInterface::class, $collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testCount(): void
    {
        $collection = $this->createCollection();

        $this->assertSame(5, $collection->count());
    }

    public function testIteration(): void
    {
        $collection = $this->createCollection();
        $items = [];

        foreach ($collection as $item) {
            $items[] = $item;
        }

        $this->assertCount(5, $items);
        foreach ($items as $item) {
            $this->assertInstanceOf(AttributeInfo::class, $item);
        }
    }

    public function testAttributeFilterSingle(): void
    {
        $collection = $this->createCollection();

        $result = $collection->attribute('App\\Attribute\\Route');

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertSame(3, $result->count());
    }

    public function testAttributeFilterMultiple(): void
    {
        $collection = $this->createCollection();

        $result = $collection->attribute('App\\Attribute\\Route', 'App\\Attribute\\Column');

        $this->assertSame(4, $result->count());
    }

    public function testAttributeFilterNoMatch(): void
    {
        $collection = $this->createCollection();

        $result = $collection->attribute('NonExistent\\Attribute');

        $this->assertSame(0, $result->count());
    }

    public function testNamespaceFilterExact(): void
    {
        $collection = $this->createCollection();

        $result = $collection->namespace('App\\Controller\\UsersController');

        $this->assertSame(2, $result->count());
    }

    public function testNamespaceFilterWildcard(): void
    {
        $collection = $this->createCollection();

        $result = $collection->namespace('App\\Controller\\*');

        $this->assertSame(3, $result->count());
    }

    public function testNamespaceFilterMultiplePatterns(): void
    {
        $collection = $this->createCollection();

        $result = $collection->namespace('App\\Controller\\*', 'App\\Model\\*');

        $this->assertSame(4, $result->count());
    }

    public function testTargetTypeSingle(): void
    {
        $collection = $this->createCollection();

        $result = $collection->targetType(AttributeTargetType::METHOD);

        $this->assertSame(2, $result->count());
    }

    public function testTargetTypeMultiple(): void
    {
        $collection = $this->createCollection();

        $result = $collection->targetType(AttributeTargetType::METHOD, AttributeTargetType::PROPERTY);

        $this->assertSame(3, $result->count());
    }

    public function testClassNameSingle(): void
    {
        $collection = $this->createCollection();

        $result = $collection->className('App\\Controller\\UsersController');

        $this->assertSame(2, $result->count());
    }

    public function testClassNameMultiple(): void
    {
        $collection = $this->createCollection();

        $result = $collection->className(
            'App\\Controller\\UsersController',
            'App\\Model\\Entity\\User',
        );

        $this->assertSame(3, $result->count());
    }

    public function testChainingFilters(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('App\\Attribute\\Route')
            ->targetType(AttributeTargetType::METHOD);

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertSame(2, $result->count());
    }

    public function testChainingMultipleFilters(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('App\\Attribute\\Route')
            ->namespace('App\\Controller\\*')
            ->targetType(AttributeTargetType::METHOD);

        $this->assertSame(2, $result->count());

        // Verify specific attributes
        $names = [];
        foreach ($result as $attr) {
            $names[] = $attr->target->targetName;
        }

        sort($names);
        $this->assertSame(['add', 'view'], $names);
    }

    public function testChainingWithStandardCollectionMethods(): void
    {
        $collection = $this->createCollection();

        // Chain custom filter with standard Collection methods
        $result = $collection
            ->attribute('App\\Attribute\\Route')
            ->filter(fn(AttributeInfo $attr): bool => $attr->arguments['method'] === 'GET');

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertSame(2, $result->count());
    }

    public function testGroupBy(): void
    {
        $collection = $this->createCollection();

        $grouped = $collection
            ->attribute('App\\Attribute\\Route')
            ->groupBy(fn(AttributeInfo $attr): string => $attr->className);

        $result = $grouped->toArray();

        $this->assertArrayHasKey('App\\Controller\\UsersController', $result);
        $this->assertArrayHasKey('App\\Controller\\Api\\PostsController', $result);
        $this->assertCount(2, $result['App\\Controller\\UsersController']);
        $this->assertCount(1, $result['App\\Controller\\Api\\PostsController']);
    }

    public function testMap(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('App\\Attribute\\Route')
            ->map(fn(AttributeInfo $attr): string => $attr->arguments['path'])
            ->toList();

        $this->assertSame(['/users', '/users/{id}', '/api/posts'], $result);
    }

    public function testExtract(): void
    {
        $collection = $this->createCollection();

        $result = $collection->targetType(AttributeTargetType::METHOD);

        $names = [];
        foreach ($result as $attr) {
            $names[] = $attr->target->targetName;
        }

        $this->assertSame(['view', 'add'], $names);
    }

    public function testFirst(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('App\\Attribute\\Column')
            ->first();

        $this->assertInstanceOf(AttributeInfo::class, $result);
        $this->assertSame('App\\Attribute\\Column', $result->attributeName);
    }

    public function testFirstOnEmpty(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('NonExistent')
            ->first();

        $this->assertNull($result);
    }

    public function testEmptyCollection(): void
    {
        $collection = new AttributeCollection([]);

        $this->assertSame(0, $collection->count());
        $this->assertTrue($collection->isEmpty());
        $this->assertNull($collection->first());
    }

    public function testToList(): void
    {
        $collection = $this->createCollection();

        $result = $collection
            ->attribute('App\\Attribute\\Inject')
            ->toList();

        $this->assertCount(1, $result);
        $this->assertSame('App\\Attribute\\Inject', $result[0]->attributeName);
    }

    public function testImmutability(): void
    {
        $collection = $this->createCollection();

        $filtered = $collection->attribute('App\\Attribute\\Route');

        // Original collection should be unchanged
        $this->assertSame(5, $collection->count());
        $this->assertSame(3, $filtered->count());
    }

    public function testSortBy(): void
    {
        $collection = $this->createCollection();

        $sorted = $collection
            ->attribute('App\\Attribute\\Route')
            ->sortBy(fn(AttributeInfo $attr): int => $attr->lineNumber, SORT_ASC);

        // sortBy returns a SortIterator which is still a CollectionInterface
        $this->assertInstanceOf(CollectionInterface::class, $sorted);

        $result = $sorted->toList();
        $this->assertSame(10, $result[0]->lineNumber);
        $this->assertSame(15, $result[1]->lineNumber);
        $this->assertSame(20, $result[2]->lineNumber);
    }

    public function testUnique(): void
    {
        $collection = $this->createCollection();

        $unique = $collection
            ->unique(fn(AttributeInfo $attr): string => $attr->attributeName);

        // Should have 3 unique attribute names: Route, Column, Inject
        $this->assertSame(3, $unique->count());
    }

    public function testMatchesNamespaceWithDeepWildcard(): void
    {
        $collection = $this->createCollection();

        // App\Controller\* should match App\Controller\Api\PostsController
        $result = $collection->namespace('App\\Controller\\*');

        $classNames = [];
        foreach ($result as $attr) {
            $classNames[] = $attr->className;
        }

        $this->assertContains('App\\Controller\\Api\\PostsController', $classNames);
    }

    public function testAttributeContains(): void
    {
        $collection = $this->createCollection();

        $result = $collection->attributeContains('Route');

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertSame(3, $result->count());
    }

    public function testAttributeContainsNoMatch(): void
    {
        $collection = $this->createCollection();

        $result = $collection->attributeContains('NonExistent');

        $this->assertSame(0, $result->count());
    }

    public function testClassNameContains(): void
    {
        $collection = $this->createCollection();

        $result = $collection->classNameContains('Controller');

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertSame(3, $result->count());
    }

    public function testClassNameContainsNoMatch(): void
    {
        $collection = $this->createCollection();

        $result = $collection->classNameContains('NonExistent');

        $this->assertSame(0, $result->count());
    }

    public function testClassNameContainsPartialMatch(): void
    {
        $collection = $this->createCollection();

        // Should match App\Controller\Api\PostsController
        $result = $collection->classNameContains('Api');

        $this->assertSame(1, $result->count());
    }

    public function testNewCollectionReturnsAttributeCollection(): void
    {
        $collection = $this->createCollection();

        // Using filter() which internally calls newCollection
        $filtered = $collection->filter(fn(AttributeInfo $attr): bool => true);

        $this->assertInstanceOf(AttributeCollection::class, $filtered);
    }
}
