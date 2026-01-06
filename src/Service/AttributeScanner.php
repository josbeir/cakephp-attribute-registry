<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Cake\Log\Log;
use Generator;
use Throwable;

/**
 * Service for scanning files and discovering PHP attributes.
 */
class AttributeScanner
{
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Constructor for AttributeScanner.
     *
     * @param \AttributeRegistry\Service\AttributeParser $parser Attribute parser
     * @param \AttributeRegistry\Service\PathResolver $pathResolver Path resolver
     * @param array<string, mixed> $config Scanner configuration
     */
    public function __construct(
        private AttributeParser $parser,
        private PathResolver $pathResolver,
        array $config = [],
    ) {
        $this->config = array_merge([
            'paths' => ['src/**/*.php'],
        ], $config);
    }

    /**
     * Scan all configured paths and return discovered attributes.
     *
     * @return \Generator<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function scanAll(): Generator
    {
        foreach ($this->pathResolver->resolveAllPaths($this->config['paths']) as $filePath) {
            foreach ($this->scanFile($filePath) as $attributeInfo) {
                yield $attributeInfo;
            }
        }
    }

    /**
     * Scan a single file for attributes.
     *
     * @param string $filePath File path to scan
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function scanFile(string $filePath): array
    {
        try {
            return $this->parser->parseFile($filePath);
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Failed to parse file %s: ', $filePath) . $throwable->getMessage());

            return [];
        }
    }
}
