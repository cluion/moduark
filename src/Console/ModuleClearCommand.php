<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Illuminate\Console\Command;
use RuntimeException;

final class ModuleClearCommand extends Command
{
    /** @var string */
    protected $signature = 'module:clear';

    /** @var string */
    protected $description = 'Remove the cached Module discovery and architecture metadata';

    public function __construct(private readonly ModuleCacheStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->store->clear();
        } catch (RuntimeException $exception) {
            $this->components->error('Module cache could not be cleared: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        $this->components->info('Module cache cleared successfully.');

        return ExitPolicy::SUCCESS;
    }
}
