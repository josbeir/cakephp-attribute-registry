<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributesInspectCommand;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class AttributesInspectCommandTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistry $registry;

    private AttributesInspectCommand $command;

    private StubConsoleOutput $out;

    private StubConsoleOutput $err;

    private ConsoleIo $io;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadTestAttributes();

        $this->registry = $this->createRegistry('attribute_test', true);
        $this->command = new AttributesInspectCommand($this->registry);

        $this->out = new StubConsoleOutput();
        $this->err = new StubConsoleOutput();
        $this->io = new ConsoleIo($this->out, $this->err);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Create Arguments from an array of command line args.
     *
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

    public function testInspectByAttributeFindsMatches(): void
    {
        $args = $this->createArgs(['TestRoute'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Found', $this->out->output());
        $this->assertStringContainsString('TestRoute', $this->out->output());
    }

    public function testInspectByAttributeNoMatchesReturnsError(): void
    {
        $args = $this->createArgs(['NonExistentAttribute'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('No attributes found', $this->err->output());
    }

    public function testInspectByClassFindsMatches(): void
    {
        $args = $this->createArgs([], ['class' => 'TestController']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Found', $this->out->output());
        $this->assertStringContainsString('TestController', $this->out->output());
    }

    public function testInspectByClassNoMatchesReturnsError(): void
    {
        $args = $this->createArgs([], ['class' => 'NonExistentClass']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('No attributes found', $this->err->output());
    }

    public function testInspectWithoutArgumentOrOptionReturnsError(): void
    {
        $args = $this->createArgs([], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('provide an attribute name or use --class', $this->err->output());
    }

    public function testInspectDisplaysAttributeDetails(): void
    {
        $args = $this->createArgs(['TestRoute'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Class:', $this->out->output());
        $this->assertStringContainsString('Target:', $this->out->output());
        $this->assertStringContainsString('File:', $this->out->output());
    }

    public function testInspectByClassDisplaysAllAttributesOnClass(): void
    {
        $args = $this->createArgs([], ['class' => 'TestController']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // TestController has TestRoute (class), TestColumn (properties), TestGet (methods)
        $this->assertStringContainsString('TestRoute', $output);
        $this->assertStringContainsString('TestColumn', $output);
        $this->assertStringContainsString('TestGet', $output);
    }

    public function testClassOptionIsRegistered(): void
    {
        $parser = $this->command->getOptionParser();
        $options = $parser->options();

        $this->assertArrayHasKey('class', $options);
        $this->assertSame('c', $options['class']->short());
    }

    public function testInspectDisplaysArrayArguments(): void
    {
        $args = $this->createArgs(['TestConfig'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // Should display array as JSON
        $this->assertStringContainsString('Arguments:', $output);
        $this->assertStringContainsString('options:', $output);
    }

    public function testInspectDisplaysBoolArguments(): void
    {
        $args = $this->createArgs(['TestConfig'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // Should display bool values as 'true' or 'false'
        $this->assertStringContainsString('enabled:', $output);
        $this->assertMatchesRegularExpression('/enabled:\s*(true|false)/', $output);
    }

    public function testInspectDisplaysNullArguments(): void
    {
        $args = $this->createArgs(['TestConfig'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // Should display null values as 'null'
        $this->assertStringContainsString('name:', $output);
        $this->assertStringContainsString('null', $output);
    }

    public function testInspectDisplaysBackedEnumArguments(): void
    {
        $args = $this->createArgs(['TestWithEnum'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // Should display backed enum value (TestCategory::Text = 'text')
        $this->assertStringContainsString('category:', $output);
        $this->assertStringContainsString('text', $output);
    }

    public function testInspectDisplaysUnitEnumArguments(): void
    {
        $args = $this->createArgs(['TestWithEnum'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributesInspectCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        // Should display unit enum name (TestPriority::High)
        $this->assertStringContainsString('priority:', $output);
        $this->assertStringContainsString('High', $output);
    }

    public function testDefaultNameReturnsCorrectValue(): void
    {
        $this->assertSame('attributes inspect', AttributesInspectCommand::defaultName());
    }

    public function testGetDescriptionReturnsString(): void
    {
        $description = AttributesInspectCommand::getDescription();
        $this->assertNotEmpty($description);
    }
}
