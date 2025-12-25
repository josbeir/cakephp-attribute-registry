<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributesCacheCommand;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class AttributesCacheCommandTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistry $registry;

    private AttributesCacheCommand $command;

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

        $this->loadTestAttributes();

        $this->registry = $this->createRegistry('attribute_test', true);
        $this->command = new AttributesCacheCommand($this->registry);

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

        // Apply default values for options
        $defaults = [
            'no-clear' => false,
            'clear-only' => false,
        ];
        $options = array_merge($defaults, $options);

        return new Arguments(
            $args,
            $options,
            $parser->argumentNames(),
        );
    }

    public function testCacheCommandReturnsSuccess(): void
    {
        $args = $this->createArgs();
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesCacheCommand::CODE_SUCCESS, $result);
    }

    public function testCacheCommandClearsCache(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Clearing attribute cache', $output);
    }

    public function testCacheCommandOutputsDiscoveryMessage(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovering attributes', $output);
    }

    public function testCacheCommandOutputsAttributeCount(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovered', $output);
        $this->assertStringContainsString('attributes', $output);
    }

    public function testCacheCommandOutputsElapsedTime(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // Should contain time in seconds format like "0.123s"
        $this->assertMatchesRegularExpression('/\d+\.?\d*s/', $output);
    }

    public function testCacheCommandActuallycacheAttributes(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        // After discover, the registry should have attributes cached
        $attributes = $this->registry->discover();
        $this->assertNotEmpty($attributes);
    }

    public function testDefaultNameIsCorrect(): void
    {
        $this->assertSame('attributes cache', AttributesCacheCommand::defaultName());
    }

    public function testOptionParserHasDescription(): void
    {
        $parser = $this->command->getOptionParser();

        $this->assertStringContainsString('cache', $parser->getDescription());
    }

    public function testCacheCommandShowsWarningWhenCacheIsDisabled(): void
    {
        // Create a new registry with disabled cache
        $registry = $this->createRegistry('attribute_test', false);
        $command = new AttributesCacheCommand($registry);

        $args = $this->createArgs();
        $command->execute($args, $this->io);

        $output = $this->err->output();
        $this->assertStringContainsString('Cache is disabled', $output);
    }

    public function testCacheCommandNoWarningWhenCacheIsEnabled(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->err->output();
        $this->assertStringNotContainsString('Cache is disabled', $output);
    }

    public function testCacheCommandWithoutClearCacheDoesNotClearCache(): void
    {
        // cache once to populate cache
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        // Get initial cache state
        $this->registry->discover();

        // Execute again with --no-clear-cache
        $this->out = new StubConsoleOutput();
        $this->err = new StubConsoleOutput();
        $this->io = new ConsoleIo($this->out, $this->err);

        $args = $this->createArgs([], ['no-clear' => true]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringNotContainsString('Clearing attribute cache', $output);
    }

    public function testCacheCommandWithClearCacheClears(): void
    {
        // cache once to populate cache - cache clearing is default behavior
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Clearing attribute cache', $output);
    }

    public function testCacheCommandWithNocacheSkipscachey(): void
    {
        $args = $this->createArgs([], ['clear-only' => true]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // Should clear cache (default behavior)
        $this->assertStringContainsString('Clearing attribute cache', $output);
        // Should NOT discover
        $this->assertStringNotContainsString('Discovering attributes', $output);
        $this->assertStringNotContainsString('Discovered', $output);
    }

    public function testCacheCommandWithoutClearOnlyDiscoveryRuns(): void
    {
        $args = $this->createArgs([], ['clear-only' => false]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovering attributes', $output);
        $this->assertStringContainsString('Discovered', $output);
    }
}
