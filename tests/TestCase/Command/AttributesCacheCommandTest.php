<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributesCacheCommand;
use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use AttributeRegistry\ValueObject\AttributeInfo;
use AttributeRegistry\ValueObject\AttributeTarget;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;
use ReflectionClass;

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
            'validate' => false,
        ];
        $options = array_merge($defaults, $options);

        return new Arguments(
            $args,
            $options,
            $parser->argumentNames(),
        );
    }

    private function createTestAttribute(string $filePath, int $time = 0): AttributeInfo
    {
        return new AttributeInfo(
            className: 'Test\\Class',
            attributeName: 'TestAttribute',
            arguments: [],
            filePath: $filePath,
            lineNumber: 1,
            target: new AttributeTarget(
                type: AttributeTargetType::CLASS_TYPE,
                targetName: 'Class',
            ),
            fileTime: $time,
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

    public function testCacheCommandWithValidateOptionValidatesCache(): void
    {
        $args = $this->createArgs([], ['validate' => true]);
        $result = $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Validating cache integrity', $output);
        $this->assertStringContainsString('Cache validation passed', $output);
        $this->assertEquals(AttributesCacheCommand::CODE_SUCCESS, $result);
    }

    public function testCacheCommandWithoutValidateOptionSkipsValidation(): void
    {
        $args = $this->createArgs([], ['validate' => false]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringNotContainsString('Validating cache integrity', $output);
        $this->assertStringNotContainsString('Cache validation', $output);
    }

    public function testCacheCommandWithValidateShowsAttributeAndFileCount(): void
    {
        $args = $this->createArgs([], ['validate' => true]);
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertMatchesRegularExpression('/Cache validation passed: \d+ attributes, \d+ files/', $output);
    }

    public function testCacheCommandWithValidateReturnsErrorOnValidationFailure(): void
    {
        // First, discover and cache attributes
        $this->registry->discover();

        // Inject invalid data - attribute with non-existent file
        $attr = $this->createTestAttribute('/tmp/non_existent_file_' . uniqid() . '.php', 123456789);
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('discoveredAttributes');
        $property->setValue($this->registry, [$attr]);

        // Run command with validation
        $args = $this->createArgs([], ['validate' => true, 'no-clear' => true]);
        $result = $this->command->execute($args, $this->io);

        // Check error output
        $errorOutput = $this->err->output();
        $this->assertStringContainsString('Cache validation failed', $errorOutput);
        $this->assertStringContainsString('File not found', $errorOutput);

        // Should return error code
        $this->assertEquals(AttributesCacheCommand::CODE_ERROR, $result);
    }
}
