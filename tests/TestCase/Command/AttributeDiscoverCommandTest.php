<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeRegistry;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class AttributeDiscoverCommandTest extends TestCase
{
    private AttributeRegistry $registry;

    private AttributeDiscoverCommand $command;

    private StubConsoleOutput $out;

    private StubConsoleOutput $err;

    private ConsoleIo $io;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::setConfig('attribute_test', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $testDataPath = dirname(__DIR__, 2) . '/data';

        // Load test attributes
        require_once $testDataPath . '/TestAttributes.php';

        $pathResolver = new PathResolver($testDataPath);
        $cache = new AttributeCache('attribute_test');
        $parser = new AttributeParser();

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 1024 * 1024,
            ],
        );

        $this->registry = new AttributeRegistry($scanner, $cache);
        $this->command = new AttributeDiscoverCommand($this->registry);

        $this->out = new StubConsoleOutput();
        $this->err = new StubConsoleOutput();
        $this->io = new ConsoleIo($this->out, $this->err);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Cache::clear('attribute_test');
        Cache::drop('attribute_test');
    }

    /**
     * @param array<int, string> $args Arguments
     * @param array<string, mixed> $options Options
     */
    private function createArgs(array $args = [], array $options = []): Arguments
    {
        $parser = $this->command->getOptionParser();

        return new Arguments(
            $args,
            $options,
            $parser->argumentNames(),
        );
    }

    public function testDiscoverCommandReturnsSuccess(): void
    {
        $args = $this->createArgs();
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeDiscoverCommand::CODE_SUCCESS, $result);
    }

    public function testDiscoverCommandClearsCache(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Clearing attribute cache', $output);
    }

    public function testDiscoverCommandOutputsDiscoveryMessage(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovering attributes', $output);
    }

    public function testDiscoverCommandOutputsAttributeCount(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovered', $output);
        $this->assertStringContainsString('attributes', $output);
    }

    public function testDiscoverCommandOutputsElapsedTime(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // Should contain time in seconds format like "0.123s"
        $this->assertMatchesRegularExpression('/\d+\.?\d*s/', $output);
    }

    public function testDiscoverCommandActuallyDiscoverAttributes(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        // After discover, the registry should have attributes cached
        $attributes = $this->registry->discover();
        $this->assertNotEmpty($attributes);
    }

    public function testDefaultNameIsCorrect(): void
    {
        $this->assertSame('attribute discover', AttributeDiscoverCommand::defaultName());
    }

    public function testOptionParserHasDescription(): void
    {
        $parser = $this->command->getOptionParser();

        $this->assertStringContainsString('Discover', $parser->getDescription());
    }

    public function testDiscoverCommandShowsWarningWhenCacheIsDisabled(): void
    {
        // Create a new registry with disabled cache
        $testDataPath = dirname(__DIR__, 2) . '/data';
        $pathResolver = new PathResolver($testDataPath);
        $cache = new AttributeCache('attribute_test', false);
        $parser = new AttributeParser();

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 1024 * 1024,
            ],
        );

        $registry = new AttributeRegistry($scanner, $cache);
        $command = new AttributeDiscoverCommand($registry);

        $args = $this->createArgs();
        $command->execute($args, $this->io);

        $output = $this->err->output();
        $this->assertStringContainsString('Cache is disabled', $output);
    }

    public function testDiscoverCommandNoWarningWhenCacheIsEnabled(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->err->output();
        $this->assertStringNotContainsString('Cache is disabled', $output);
    }
}
