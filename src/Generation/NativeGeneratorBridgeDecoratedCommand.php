<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class NativeGeneratorBridgeDecoratedCommand extends Command
{
    /** @var array<string, InputOption> */
    private array $nativeOptions;

    public function __construct(
        private readonly ModuleMakerType $type,
        private readonly Command $original,
        private readonly NativeGeneratorBridgeExecutor $executor,
    ) {
        parent::__construct($original->getName());

        $definition = clone $original->getNativeDefinition();
        $this->nativeOptions = [];

        foreach ($definition->getOptions() as $option) {
            $this->nativeOptions[$option->getName()] = $option;
        }
        $definition->addOption(new InputOption(
            'module',
            null,
            InputOption::VALUE_REQUIRED,
            'Generate the artifact inside the named Moduark Module',
        ));

        $this->setDefinition($definition);
        $aliases = array_values(array_filter(
            $original->getAliases(),
            static fn (mixed $alias): bool => is_string($alias),
        ));
        $this->setAliases($aliases);
        $this->setDescription($original->getDescription());
        $this->setHelp($original->getHelp());
        $this->setHidden($original->isHidden());
    }

    public function original(): Command
    {
        return $this->original;
    }

    public function generatorType(): ModuleMakerType
    {
        return $this->type;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        if (! $input->hasParameterOption('--module', true)) {
            return $this->original->run($input, $output);
        }

        return parent::run($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executor->execute($this->type, $this->nativeOptions, $input, $output);
    }
}
