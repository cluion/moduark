<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Generation\ModuleMakerType;
use Cluion\Moduark\Generation\NativeGeneratorBridgeDecoratedCommand;
use Cluion\Moduark\Generation\NativeGeneratorBridgeCommandSet;
use Cluion\Moduark\Generation\NativeGeneratorBridgeExecutor;
use Illuminate\Console\Application;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class NativeGeneratorBridgeMutationTest extends TestCase
{
    public function test_command_set_rolls_back_every_original_after_partial_registration(): void
    {
        $container = new Container;
        $application = new class($container, new Dispatcher($container), 'test') extends Application
        {
            private int $decoratedAdds = 0;

            private bool $armed = false;

            public function arm(): void
            {
                $this->armed = true;
            }

            public function add(Command $command): ?Command
            {
                if ($this->armed
                    && $command instanceof NativeGeneratorBridgeDecoratedCommand
                    && ++$this->decoratedAdds === 2) {
                    $this->armed = false;

                    throw new \RuntimeException('Injected registration failure.');
                }

                return parent::add($command);
            }
        };
        $executor = new NativeGeneratorBridgeExecutor($this->createStub(Kernel::class));
        $types = [
            ModuleMakerType::PhpCast,
            ModuleMakerType::Channel,
            ModuleMakerType::PhpTrait,
        ];
        $originals = [];
        $decorated = [];

        foreach ($types as $type) {
            $original = new Command($type->command());
            $original->addArgument('name', InputArgument::REQUIRED);
            $application->add($original);
            $originals[$type->command()] = $original;
            $decorated[$type->command()] = new NativeGeneratorBridgeDecoratedCommand(
                $type,
                $original,
                $executor,
            );
        }

        $application->arm();
        $message = (new NativeGeneratorBridgeCommandSet)->replace(
            $application,
            $originals,
            $decorated,
        );

        self::assertSame('Injected registration failure.', $message);

        foreach ($originals as $name => $original) {
            self::assertSame($original, $application->get($name));
            self::assertFalse($original->getNativeDefinition()->hasOption('module'));
        }
    }

    public function test_missing_module_delegates_the_exact_input_and_output_to_the_original_command(): void
    {
        $original = new class extends Command
        {
            public ?InputInterface $receivedInput = null;

            public ?OutputInterface $receivedOutput = null;

            public function __construct()
            {
                parent::__construct('make:trait');
                $this->addArgument('name', InputArgument::REQUIRED);
            }

            public function run(InputInterface $input, OutputInterface $output): int
            {
                $this->receivedInput = $input;
                $this->receivedOutput = $output;

                return 17;
            }
        };
        $executor = new NativeGeneratorBridgeExecutor($this->createStub(Kernel::class));
        $decorated = new NativeGeneratorBridgeDecoratedCommand(
            ModuleMakerType::PhpTrait,
            $original,
            $executor,
        );
        $input = new ArrayInput(['name' => 'Probe']);
        $output = new BufferedOutput;

        self::assertSame(17, $decorated->run($input, $output));
        self::assertSame($input, $original->receivedInput);
        self::assertSame($output, $original->receivedOutput);
        self::assertFalse($original->getNativeDefinition()->hasOption('module'));
        self::assertTrue($decorated->getNativeDefinition()->hasOption('module'));
    }

    public function test_module_execution_reuses_the_moduark_make_entrypoint(): void
    {
        $output = new BufferedOutput;
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(self::once())
            ->method('call')
            ->with(
                'moduark:make',
                [
                    'module' => 'User',
                    'type' => 'trait',
                    'name' => 'RecordsActivity',
                    '--force' => true,
                ],
                $output,
            )
            ->willReturn(Command::SUCCESS);
        $executor = new NativeGeneratorBridgeExecutor($kernel);
        [$definition, $options] = $this->definition([
            new InputOption('force', 'f'),
        ]);
        $input = new ArrayInput([
            'name' => 'RecordsActivity',
            '--module' => 'User',
            '--force' => true,
        ], $definition);

        self::assertSame(Command::SUCCESS, $executor->execute(
            ModuleMakerType::PhpTrait,
            $options,
            $input,
            $output,
        ));
    }

    public function test_unsupported_native_option_fails_closed_before_generation(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(self::never())->method('call');
        $executor = new NativeGeneratorBridgeExecutor($kernel);
        [$definition, $options] = $this->definition([
            new InputOption('singleton'),
        ]);
        $input = new ArrayInput([
            'name' => 'AccountController',
            '--module' => 'User',
            '--singleton' => true,
        ], $definition);
        $output = new BufferedOutput;

        self::assertSame(ExitPolicy::TOOL_ERROR, $executor->execute(
            ModuleMakerType::Controller,
            $options,
            $input,
            $output,
        ));
        self::assertStringContainsString(
            'The --singleton option is not supported for Maker type [controller].',
            $output->fetch(),
        );
    }

    /**
     * @param list<InputOption> $nativeOptions
     * @return array{InputDefinition, array<string, InputOption>}
     */
    private function definition(array $nativeOptions): array
    {
        $definition = new InputDefinition([
            new InputArgument('name', InputArgument::REQUIRED),
            ...$nativeOptions,
            new InputOption('module', null, InputOption::VALUE_REQUIRED),
        ]);

        $options = [];

        foreach ($nativeOptions as $option) {
            $options[$option->getName()] = $option;
        }

        return [$definition, $options];
    }
}
