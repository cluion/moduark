<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Graph\ModuleGraphNode;
use Cluion\Moduark\Inspection\ModuleInspection;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class ModuleInspectCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:inspect
        {module : Module name to inspect}';

    /** @var string */
    protected $description = 'Inspect one Module and its architecture metadata';

    public function __construct(private readonly ModuleInspectionBuilder $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');

        if (! is_string($module) || trim($module) === '') {
            $this->components->error('The module argument must be a non-empty Module name.');

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $inspection = $this->inspector->build($module);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error('Module inspection failed: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        $this->table(['Field', 'Value'], $this->rows($inspection));

        return self::SUCCESS;
    }

    /**
     * @return list<array{string, string}>
     */
    private function rows(ModuleInspection $inspection): array
    {
        $module = $inspection->module();
        $descriptor = $inspection->descriptor();
        $level = $inspection->level();

        $dependencies = array_map(
            static fn (ModuleGraphNode $dependency): string => sprintf(
                '%s <%s> (%s)',
                $dependency->name(),
                $dependency->moduleClass(),
                $dependency->discovered() ? 'discovered' : 'missing',
            ),
            $inspection->dependencies(),
        );
        $missing = array_map(
            static fn (ModuleGraphNode $dependency): string => sprintf(
                '%s <%s>',
                $dependency->name(),
                $dependency->moduleClass(),
            ),
            $inspection->missingDependencies(),
        );
        $requirements = array_map(
            fn (CapabilityRequirement $requirement): string => $this->requirement(
                $inspection,
                $requirement,
            ),
            $descriptor->requires(),
        );
        $capabilities = array_map(
            fn (string $capability): string => sprintf(
                '%s <%s>',
                $this->shortName($capability),
                $capability,
            ),
            $descriptor->provides(),
        );
        $publicApi = array_map(
            static fn (SourceSymbol $symbol): string => $symbol->name(),
            $inspection->publicApi(),
        );

        return [
            ['Name', $module->name()],
            ['Class', $module->moduleClass()],
            ['Path', $module->path()],
            ['Namespace', $module->namespace()],
            ['State', 'enabled'],
            ['Level', $level->value.' ('.$level->label().')'],
            ['Dependencies', $this->list($dependencies)],
            ['Missing dependencies', $this->list($missing)],
            ['Service providers', $this->list($descriptor->providers())],
            ['Requires', $this->list($requirements)],
            ['Provides', $this->list($capabilities)],
            ['Owned tables', $this->list($inspection->ownedTables())],
            ['Explicit exports', $this->list($descriptor->exports())],
            ['Public API (convention)', $this->list($publicApi)],
        ];
    }

    private function requirement(
        ModuleInspection $inspection,
        CapabilityRequirement $requirement,
    ): string {
        $provider = $inspection->capabilityProvider($requirement->capability());

        return sprintf(
            '%s <%s> | Provider: %s <%s> | Port: %s | Adapter: %s',
            $this->shortName($requirement->capability()),
            $requirement->capability(),
            $provider->name(),
            $provider->moduleClass(),
            $requirement->port(),
            $requirement->adapter(),
        );
    }

    /** @param list<string> $values */
    private function list(array $values): string
    {
        return $values === [] ? '—' : implode(PHP_EOL, $values);
    }

    private function shortName(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}
