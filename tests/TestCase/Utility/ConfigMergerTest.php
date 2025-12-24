<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Utility;

use AttributeRegistry\Utility\ConfigMerger;
use Cake\TestSuite\TestCase;

/**
 * ConfigMerger Test Case
 */
class ConfigMergerTest extends TestCase
{
    public function testMergeWithEmptyArrays(): void
    {
        $result = ConfigMerger::merge([], []);
        $this->assertSame([], $result);
    }

    public function testMergeWithEmptyDefaults(): void
    {
        $overrides = ['key' => 'value'];
        $result = ConfigMerger::merge([], $overrides);
        $this->assertSame($overrides, $result);
    }

    public function testMergeWithEmptyOverrides(): void
    {
        $defaults = ['key' => 'value'];
        $result = ConfigMerger::merge($defaults, []);
        $this->assertSame($defaults, $result);
    }

    public function testMergeScalarValuesUserTakesPrecedence(): void
    {
        $defaults = ['enabled' => true, 'debug' => false];
        $overrides = ['enabled' => false];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['debug']);
    }

    public function testMergeNestedAssociativeArrays(): void
    {
        $defaults = [
            'cache' => [
                'enabled' => true,
                'path' => '/default/path',
                'validateFiles' => false,
            ],
        ];
        $overrides = [
            'cache' => [
                'enabled' => false,
            ],
        ];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertFalse($result['cache']['enabled']);
        $this->assertSame('/default/path', $result['cache']['path']);
        $this->assertFalse($result['cache']['validateFiles']);
    }

    public function testMergeSequentialArraysReplace(): void
    {
        $defaults = [
            'paths' => ['src/**/*.php', 'lib/**/*.php'],
        ];
        $overrides = [
            'paths' => ['custom/**/*.php'],
        ];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertSame(['custom/**/*.php'], $result['paths']);
        $this->assertCount(1, $result['paths']);
    }

    public function testMergeComplexNestedStructure(): void
    {
        $defaults = [
            'cache' => [
                'enabled' => true,
                'path' => '/default',
            ],
            'scanner' => [
                'paths' => ['src/**', 'lib/**'],
                'exclude_paths' => ['vendor/**', 'tmp/**'],
            ],
        ];
        $overrides = [
            'cache' => [
                'enabled' => false,
            ],
            'scanner' => [
                'exclude_paths' => ['custom_exclude/**'],
            ],
        ];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertFalse($result['cache']['enabled']);
        $this->assertSame('/default', $result['cache']['path']);
        $this->assertSame(['src/**', 'lib/**'], $result['scanner']['paths']);
        $this->assertSame(['custom_exclude/**'], $result['scanner']['exclude_paths']);
    }

    public function testMergeAddsNewKeys(): void
    {
        $defaults = ['key1' => 'value1'];
        $overrides = ['key2' => 'value2'];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertSame('value1', $result['key1']);
        $this->assertSame('value2', $result['key2']);
    }

    public function testMergeDeepNesting(): void
    {
        $defaults = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'default',
                        'other' => 'keep',
                    ],
                ],
            ],
        ];
        $overrides = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'override',
                    ],
                ],
            ],
        ];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertSame('override', $result['level1']['level2']['level3']['value']);
        $this->assertSame('keep', $result['level1']['level2']['level3']['other']);
    }

    public function testMergeMixedArrayTypes(): void
    {
        $defaults = [
            'config' => ['key' => 'value'], // Associative
            'list' => [1, 2, 3], // Sequential
        ];
        $overrides = [
            'config' => ['key2' => 'value2'],
            'list' => [4, 5],
        ];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertArrayHasKey('key', $result['config']);
        $this->assertArrayHasKey('key2', $result['config']);
        $this->assertSame([4, 5], $result['list']);
    }

    public function testMergeNullValues(): void
    {
        $defaults = ['key' => 'value'];
        $overrides = ['key' => null];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertNull($result['key']);
    }

    public function testMergeReplacesArrayWithScalar(): void
    {
        $defaults = ['key' => ['nested' => 'value']];
        $overrides = ['key' => 'scalar'];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertSame('scalar', $result['key']);
    }

    public function testMergeReplacesScalarWithArray(): void
    {
        $defaults = ['key' => 'scalar'];
        $overrides = ['key' => ['nested' => 'value']];
        $result = ConfigMerger::merge($defaults, $overrides);

        $this->assertIsArray($result['key']);
        $this->assertSame('value', $result['key']['nested']);
    }
}
