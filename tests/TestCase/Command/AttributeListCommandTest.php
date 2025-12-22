<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Command\AttributeListCommand;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;

class AttributeListCommandTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistry $registry;

    private AttributeListCommand $command;

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
        $this->command = new AttributeListCommand($this->registry);

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

    public function testListAllAttributesReturnsSuccess(): void
    {
        $args = $this->createArgs();
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
    }

    public function testListAllAttributesOutputsCount(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        $this->assertStringContainsString('Found', $output);
        $this->assertStringContainsString('attributes', $output);
    }

    public function testListAllAttributesOutputsTable(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // Table headers
        $this->assertStringContainsString('Attribute', $output);
        $this->assertStringContainsString('Class', $output);
        $this->assertStringContainsString('Type', $output);
        $this->assertStringContainsString('Target', $output);
    }

    public function testFilterByAttributeOption(): void
    {
        $args = $this->createArgs([], ['attribute' => 'TestRoute']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        $this->assertStringContainsString('TestRoute', $output);
    }

    public function testFilterByAttributeShortOption(): void
    {
        $parser = $this->command->getOptionParser();
        $options = $parser->options();

        $this->assertArrayHasKey('attribute', $options);
        $this->assertSame('a', $options['attribute']->short());
    }

    public function testFilterByClassOption(): void
    {
        $args = $this->createArgs([], ['class' => 'TestController']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        $this->assertStringContainsString('TestController', $output);
    }

    public function testFilterByClassShortOption(): void
    {
        $parser = $this->command->getOptionParser();
        $options = $parser->options();

        $this->assertArrayHasKey('class', $options);
        $this->assertSame('c', $options['class']->short());
    }

    public function testFilterByTypeOption(): void
    {
        $args = $this->createArgs([], ['type' => 'method']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->out->output();
        $this->assertStringContainsString('method', $output);
    }

    public function testFilterByTypeShortOption(): void
    {
        $parser = $this->command->getOptionParser();
        $options = $parser->options();

        $this->assertArrayHasKey('type', $options);
        $this->assertSame('t', $options['type']->short());
    }

    public function testFilterByInvalidTypeReturnsNoResults(): void
    {
        $args = $this->createArgs([], ['type' => 'invalid_type']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->err->output();
        $this->assertStringContainsString('No attributes found', $output);
    }

    public function testFilterByNonExistentAttributeReturnsNoResults(): void
    {
        $args = $this->createArgs([], ['attribute' => 'NonExistentAttribute']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->err->output();
        $this->assertStringContainsString('No attributes found', $output);
    }

    public function testFilterByNonExistentClassReturnsNoResults(): void
    {
        $args = $this->createArgs([], ['class' => 'NonExistentClass']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeListCommand::CODE_SUCCESS, $result);
        $output = $this->err->output();
        $this->assertStringContainsString('No attributes found', $output);
    }

    public function testDefaultNameIsCorrect(): void
    {
        $this->assertSame('attribute list', AttributeListCommand::defaultName());
    }

    public function testOptionParserHasDescription(): void
    {
        $parser = $this->command->getOptionParser();

        $this->assertStringContainsString('List', $parser->getDescription());
    }

    public function testListShowsAllTargetTypes(): void
    {
        $args = $this->createArgs();
        $this->command->execute($args, $this->io);

        $output = $this->out->output();
        // TestController has class, property, and method attributes
        $this->assertStringContainsString('class', $output);
        $this->assertStringContainsString('method', $output);
        $this->assertStringContainsString('property', $output);
    }
}
