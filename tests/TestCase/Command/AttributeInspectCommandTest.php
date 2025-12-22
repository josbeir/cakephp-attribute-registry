<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Command;

use AttributeRegistry\Command\AttributeInspectCommand;
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

class AttributeInspectCommandTest extends TestCase
{
    private AttributeRegistry $registry;

    private AttributeInspectCommand $command;

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
        $this->command = new AttributeInspectCommand($this->registry);

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

        $this->assertSame(AttributeInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Found', $this->out->output());
        $this->assertStringContainsString('TestRoute', $this->out->output());
    }

    public function testInspectByAttributeNoMatchesReturnsError(): void
    {
        $args = $this->createArgs(['NonExistentAttribute'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('No attributes found', $this->err->output());
    }

    public function testInspectByClassFindsMatches(): void
    {
        $args = $this->createArgs([], ['class' => 'TestController']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Found', $this->out->output());
        $this->assertStringContainsString('TestController', $this->out->output());
    }

    public function testInspectByClassNoMatchesReturnsError(): void
    {
        $args = $this->createArgs([], ['class' => 'NonExistentClass']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('No attributes found', $this->err->output());
    }

    public function testInspectWithoutArgumentOrOptionReturnsError(): void
    {
        $args = $this->createArgs([], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_ERROR, $result);
        $this->assertStringContainsString('provide an attribute name or use --class', $this->err->output());
    }

    public function testInspectDisplaysAttributeDetails(): void
    {
        $args = $this->createArgs(['TestRoute'], []);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString('Class:', $this->out->output());
        $this->assertStringContainsString('Target:', $this->out->output());
        $this->assertStringContainsString('File:', $this->out->output());
    }

    public function testInspectByClassDisplaysAllAttributesOnClass(): void
    {
        $args = $this->createArgs([], ['class' => 'TestController']);
        $result = $this->command->execute($args, $this->io);

        $this->assertSame(AttributeInspectCommand::CODE_SUCCESS, $result);
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
}
