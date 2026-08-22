<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Cluion\Moduark\Generation\ModuleGenerator;
use Illuminate\Console\Command;

final class MakeModuleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'moduark:make-module {name : The StudlyCase name of the Module}';

    /**
     * @var string
     */
    protected $description = 'Create a minimal Module entry class';

    public function __construct(private readonly ModuleGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The Module name must be a string.');

            return self::INVALID;
        }

        try {
            $path = $this->generator->generate($name);
        } catch (ModuleGenerationFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$path}] created successfully.");

        return self::SUCCESS;
    }
}
