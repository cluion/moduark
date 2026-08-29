<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use JsonException;
use RuntimeException;

final class ModuleExportRenderer
{
    public function render(ModuleExportPlan $plan, ExportPlanFile $file): string
    {
        return match ($file->transform()) {
            'composer_metadata' => $this->composer($plan),
            'package_provider:'.$plan->provider() => $this->provider($plan),
            default => throw new RuntimeException(
                "Unsupported generated export target [{$file->destination()}].",
            ),
        };
    }

    /** @throws JsonException */
    private function composer(ModuleExportPlan $plan): string
    {
        $requirements = ['php' => '^8.2'];

        foreach ($plan->dependencies() as $dependency) {
            if ($dependency->status() !== ExportPlanDependency::RESOLVED
                || $dependency->package() === null
                || $dependency->constraint() === null) {
                throw new RuntimeException('The export plan contains an unresolved Composer dependency.');
            }

            $requirements[$dependency->package()] = $dependency->constraint();
        }

        ksort($requirements, SORT_STRING);
        $namespace = rtrim($plan->namespace(), '\\').'\\';

        return json_encode([
            '$schema' => 'https://getcomposer.org/schema.json',
            'name' => $plan->package(),
            'description' => 'Exported '.$plan->module()->name().' Module package.',
            'type' => 'library',
            'license' => 'proprietary',
            'require' => $requirements,
            'require-dev' => [
                'orchestra/testbench' => '^10.0 || ^11.0',
                'phpunit/phpunit' => '^11.5 || ^12.5 || ^13.0',
            ],
            'autoload' => [
                'psr-4' => [$namespace => 'src/'],
            ],
            'autoload-dev' => [
                'psr-4' => [$namespace.'Tests\\' => 'tests/'],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [$plan->provider()],
                ],
            ],
            'scripts' => [
                'test' => 'phpunit',
            ],
            'config' => [
                'sort-packages' => true,
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    private function provider(ModuleExportPlan $plan): string
    {
        $namespace = $plan->namespace();
        $module = $plan->module()->name();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Cluion\Moduark\Package\PortableModuleServiceProvider;

final class {$module}PackageServiceProvider extends PortableModuleServiceProvider
{
    protected function moduleClass(): string
    {
        return {$module}Module::class;
    }

    protected function modulePath(): string
    {
        return __DIR__.'/{$module}Module.php';
    }
}
PHP."\n";
    }
}
