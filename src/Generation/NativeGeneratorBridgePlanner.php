<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Command\Command;

final readonly class NativeGeneratorBridgePlanner
{
    public const MISSING_COMMAND = 'MOD-NATIVE-BRIDGE-COMMAND-001';

    public const COMMAND_OWNER_COLLISION = 'MOD-NATIVE-BRIDGE-OWNER-001';

    public const SIGNATURE_COLLISION = 'MOD-NATIVE-BRIDGE-SIGNATURE-001';

    public const OPTION_COLLISION = 'MOD-NATIVE-BRIDGE-OPTION-001';

    public const REGISTRATION_FAILED = 'MOD-NATIVE-BRIDGE-REGISTER-001';

    public const DECORATION_DRIFT = 'MOD-NATIVE-BRIDGE-DRIFT-001';

    public function __construct(
        private Kernel $kernel,
        private ModulesConfig $configuration,
        private NativeGeneratorBridgeState $state,
    ) {
    }

    public function plan(): NativeGeneratorBridgePlan
    {
        return $this->planForCommands($this->kernel->all());
    }

    /** @param array<array-key, mixed> $commands */
    public function planForCommands(array $commands): NativeGeneratorBridgePlan
    {
        $candidates = [];

        foreach (ModuleMakerType::cases() as $type) {
            $name = $type->command();
            $expectedClass = $this->expectedClass($type);
            $command = $commands[$name] ?? null;
            $diagnostics = [];

            if (! $command instanceof Command) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::MISSING_COMMAND,
                    "Laravel command [{$name}] is not registered.",
                );
                $candidates[] = new NativeGeneratorBridgeCandidate(
                    $name,
                    $type->id(),
                    $expectedClass,
                    null,
                    $diagnostics,
                );

                continue;
            }

            $decorated = $command instanceof NativeGeneratorBridgeDecoratedCommand
                && $command->generatorType() === $type;
            $inspected = $decorated ? $command->original() : $command;
            $actualClass = $inspected::class;

            if ($actualClass !== $expectedClass) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::COMMAND_OWNER_COLLISION,
                    "Command [{$name}] is owned by [{$actualClass}], expected [{$expectedClass}].",
                );
            }

            $definition = $inspected->getNativeDefinition();
            $argument = $definition->hasArgument('name')
                ? $definition->getArgument('name')
                : null;

            if (count($definition->getArguments()) !== 1
                || $argument === null
                || ! $argument->isRequired()
                || $argument->isArray()) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::SIGNATURE_COLLISION,
                    "Command [{$name}] must expose exactly one required non-array [name] argument.",
                );
            }

            if (! $decorated && $definition->hasOption('module')) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::OPTION_COLLISION,
                    "Command [{$name}] already defines the [--module] option.",
                );
            }

            if ($this->state->active() && ! $this->state->owns($name, $command)) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::DECORATION_DRIFT,
                    "Command [{$name}] no longer has the activated Moduark decorator.",
                );
            }

            if ($this->state->registrationFailure() !== null) {
                $diagnostics[] = new NativeGeneratorBridgeDiagnostic(
                    self::REGISTRATION_FAILED,
                    'Native bridge registration failed: '.$this->state->registrationFailure(),
                );
            }

            $candidates[] = new NativeGeneratorBridgeCandidate(
                $name,
                $type->id(),
                $expectedClass,
                $actualClass,
                $diagnostics,
                $decorated,
            );
        }

        usort(
            $candidates,
            static fn (NativeGeneratorBridgeCandidate $left, NativeGeneratorBridgeCandidate $right): int =>
                strcmp($left->command(), $right->command()),
        );

        return new NativeGeneratorBridgePlan(
            $this->configuration->nativeGeneratorBridgeEnabled(),
            $candidates,
        );
    }

    /** @return class-string<Command> */
    public function expectedClass(ModuleMakerType $type): string
    {
        return match ($type) {
            ModuleMakerType::PhpCast => \Illuminate\Foundation\Console\CastMakeCommand::class,
            ModuleMakerType::Channel => \Illuminate\Foundation\Console\ChannelMakeCommand::class,
            ModuleMakerType::PhpClass => \Illuminate\Foundation\Console\ClassMakeCommand::class,
            ModuleMakerType::ConsoleCommand => \Illuminate\Foundation\Console\ConsoleMakeCommand::class,
            ModuleMakerType::Component => \Illuminate\Foundation\Console\ComponentMakeCommand::class,
            ModuleMakerType::Config => \Illuminate\Foundation\Console\ConfigMakeCommand::class,
            ModuleMakerType::Model => \Illuminate\Foundation\Console\ModelMakeCommand::class,
            ModuleMakerType::Controller => \Illuminate\Routing\Console\ControllerMakeCommand::class,
            ModuleMakerType::PhpEnum => \Illuminate\Foundation\Console\EnumMakeCommand::class,
            ModuleMakerType::Event => \Illuminate\Foundation\Console\EventMakeCommand::class,
            ModuleMakerType::PhpException => \Illuminate\Foundation\Console\ExceptionMakeCommand::class,
            ModuleMakerType::Factory => \Illuminate\Database\Console\Factories\FactoryMakeCommand::class,
            ModuleMakerType::PhpInterface => \Illuminate\Foundation\Console\InterfaceMakeCommand::class,
            ModuleMakerType::Job => \Illuminate\Foundation\Console\JobMakeCommand::class,
            ModuleMakerType::JobMiddleware => \Illuminate\Foundation\Console\JobMiddlewareMakeCommand::class,
            ModuleMakerType::Listener => \Illuminate\Foundation\Console\ListenerMakeCommand::class,
            ModuleMakerType::Mail => \Illuminate\Foundation\Console\MailMakeCommand::class,
            ModuleMakerType::HttpMiddleware => \Illuminate\Routing\Console\MiddlewareMakeCommand::class,
            ModuleMakerType::Migration => \Illuminate\Database\Console\Migrations\MigrateMakeCommand::class,
            ModuleMakerType::Notification => \Illuminate\Foundation\Console\NotificationMakeCommand::class,
            ModuleMakerType::Observer => \Illuminate\Foundation\Console\ObserverMakeCommand::class,
            ModuleMakerType::Policy => \Illuminate\Foundation\Console\PolicyMakeCommand::class,
            ModuleMakerType::ServiceProvider => \Illuminate\Foundation\Console\ProviderMakeCommand::class,
            ModuleMakerType::HttpRequest => \Illuminate\Foundation\Console\RequestMakeCommand::class,
            ModuleMakerType::HttpResource => \Illuminate\Foundation\Console\ResourceMakeCommand::class,
            ModuleMakerType::ValidationRule => \Illuminate\Foundation\Console\RuleMakeCommand::class,
            ModuleMakerType::PhpScope => \Illuminate\Foundation\Console\ScopeMakeCommand::class,
            ModuleMakerType::Seeder => \Illuminate\Database\Console\Seeds\SeederMakeCommand::class,
            ModuleMakerType::Test => \Illuminate\Foundation\Console\TestMakeCommand::class,
            ModuleMakerType::PhpTrait => \Illuminate\Foundation\Console\TraitMakeCommand::class,
            ModuleMakerType::View => \Illuminate\Foundation\Console\ViewMakeCommand::class,
        };
    }
}
