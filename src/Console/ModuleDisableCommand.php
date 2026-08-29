<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Lifecycle\Activation\ModuleActivationIntent;

final class ModuleDisableCommand extends ModuleActivationCommand
{
    /** @var string */
    protected $signature = 'moduark:disable
        {module : Module name to disable}
        {--dry-run : Preview the validated activation plan without changing state}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Preview disabling a Module without changing activation state';

    protected function intent(): ModuleActivationIntent
    {
        return ModuleActivationIntent::Disable;
    }
}
