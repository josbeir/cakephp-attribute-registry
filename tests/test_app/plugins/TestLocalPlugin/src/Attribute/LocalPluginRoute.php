<?php
declare(strict_types=1);

namespace TestLocalPlugin\Attribute;

use Attribute;

/**
 * Test attribute for local plugin testing
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class LocalPluginRoute
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $method = null,
        public readonly ?string $name = null,
    ) {
    }
}
