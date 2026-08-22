<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Listing\ModuleListBuilder;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'moduark:list';

    /**
     * @var string
     */
    protected $description = 'List discovered Modules and their architecture metadata';

    public function __construct(private readonly ModuleListBuilder $list)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rows = $this->list->rows();

        if ($rows === []) {
            $this->components->info('No Modules discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Module', 'State', 'Level', 'Dependencies', 'Requires', 'Provides'],
            $rows,
        );

        return self::SUCCESS;
    }
}
