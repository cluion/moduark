<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Cache\ModuleCacheBuilder;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class ModuleCacheCommand extends Command
{
    /** @var string */
    protected $signature = 'module:cache';

    /** @var string */
    protected $description = 'Cache discovered Modules and their architecture metadata';

    public function __construct(
        private readonly ModuleCacheBuilder $builder,
        private readonly ModuleCacheStore $store,
        private readonly ModulesConfig $configuration,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $manifest = $this->builder->build($this->configuration->path());
            $this->store->write($manifest);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error('Module cache could not be created: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        $count = count($manifest->descriptors());
        $label = $count === 1 ? 'Module' : 'Modules';

        $this->components->info("Module cache created successfully: {$count} {$label} cached.");

        return ExitPolicy::SUCCESS;
    }
}
