<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Architecture\ExitPolicy;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class NativeGeneratorBridgeExecutor
{
    public function __construct(private Kernel $kernel)
    {
    }

    /** @param array<string, InputOption> $nativeOptions */
    public function execute(
        ModuleMakerType $type,
        array $nativeOptions,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $module = $input->getOption('module');
        $name = $input->getArgument('name');

        if (! is_string($module) || trim($module) === '' || ! is_string($name)) {
            $output->writeln('<error>The --module option and name argument must be non-empty strings.</error>');

            return ExitPolicy::TOOL_ERROR;
        }

        $parameters = [
            'module' => trim($module),
            'type' => $type->id(),
            'name' => $name,
        ];
        $supported = $type->supportedOptions();

        foreach ($nativeOptions as $option) {
            $optionName = $option->getName();

            if ($optionName === 'module' || ! $this->explicitlyProvided($input, $option)) {
                continue;
            }

            if (! in_array($optionName, $supported, true)) {
                $output->writeln(sprintf(
                    '<error>The --%s option is not supported for Maker type [%s].</error>',
                    $optionName,
                    $type->id(),
                ));

                return ExitPolicy::TOOL_ERROR;
            }

            $value = $input->getOption($optionName);
            $parameters['--'.$optionName] = $option->isValueOptional() && $value === null
                ? null
                : $value;
        }

        return $this->kernel->call('moduark:make', $parameters, $output);
    }

    private function explicitlyProvided(InputInterface $input, InputOption $option): bool
    {
        $names = ['--'.$option->getName()];
        $shortcut = $option->getShortcut();

        if ($shortcut !== null) {
            foreach (explode('|', $shortcut) as $name) {
                $names[] = '-'.$name;
            }
        }

        return $input->hasParameterOption($names, true)
            || $input->getOption($option->getName()) !== $option->getDefault();
    }
}
