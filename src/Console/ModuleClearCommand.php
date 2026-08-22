<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Illuminate\Console\Command;
use RuntimeException;

final class ModuleClearCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:clear';

    /** @var string */
    protected $description = 'Remove cached Module metadata and source analysis';

    public function __construct(
        private readonly ModuleCacheStore $store,
        private readonly SourceAnalysisCacheStore $sourceCache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->store->clear();
            $this->sourceCache->clear();
        } catch (RuntimeException $exception) {
            $this->components->error('Module cache could not be cleared: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        $this->components->info('Module cache cleared successfully.');

        return ExitPolicy::SUCCESS;
    }
}
