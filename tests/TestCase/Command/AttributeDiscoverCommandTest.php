<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributeDiscoverCommand;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class AttributeDiscoverCommandTest extends TestCase
{
    use AttributeRegistryTestTrait;

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

        $this->loadTestAttributes();

        $this->registry = $this->createRegistry('attribute_test', true);
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

        // Apply default values for options
        $defaults = [
            'clear-cache' => true,
        ];
        $options = array_merge($defaults, $options);

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
        $registry = $this->createRegistry('attribute_test', false);
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

    public function testDiscoverCommandWithoutClearCacheDoesNotClearCache(): void
    {
        // Discover once to populate cache
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        // Get initial cache state
        $initialAttributes = $this->registry->discover();

        // Execute again with --no-clear-cache
        $this->out = new StubConsoleOutput();
        $this->err = new StubConsoleOutput();
        $this->io = new ConsoleIo($this->out, $this->err);

        $args = $this->createArgs([], ['clear-cache' => false]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringNotContainsString('Clearing attribute cache', $output);
    }

    public function testDiscoverCommandWithClearCacheClears(): void
    {
        // Discover once to populate cache
        $args = $this->createArgs([], ['clear-cache' => true]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Clearing attribute cache', $output);
    }

    public function testDiscoverCommandWithNoDiscoverSkipsDiscovery(): void
    {
        $args = $this->createArgs([], ['clear-cache' => true, 'no-discover' => true]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // Should clear cache
        $this->assertStringContainsString('Clearing attribute cache', $output);
        // Should NOT discover
        $this->assertStringNotContainsString('Discovering attributes', $output);
        $this->assertStringNotContainsString('Discovered', $output);
    }

    public function testDiscoverCommandWithoutNoDiscoverDiscoveryRuns(): void
    {
        $args = $this->createArgs([], ['clear-cache' => true, 'no-discover' => false]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Discovering attributes', $output);
        $this->assertStringContainsString('Discovered', $output);
    }
}
