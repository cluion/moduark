<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Contracts\Foundation\Application;

final readonly class ModuleScaffoldPlanner
{
    public function __construct(
        private Application $application,
        private ModulesConfig $configuration,
        private ModuleNamespaceResolver $namespaceResolver,
    ) {
    }

    public function plan(string $rawName, ModuleScaffoldPreset $preset): GenerationPlan
    {
        $module = ModuleName::from($rawName);
        $rootPath = $this->rootPath();
        $rootNamespace = $this->namespaceResolver->resolve($rootPath);
        $modulePath = $rootPath.DIRECTORY_SEPARATOR.$module->value();
        $moduleNamespace = $rootNamespace.'\\'.$module->value();
        $targets = [];

        foreach ($preset->descriptors() as $descriptor) {
            $relativePath = $descriptor->relativePath($module);
            $targets[] = new GenerationTarget(
                $descriptor->value,
                null,
                $descriptor->identity($moduleNamespace, $module),
                $modulePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $relativePath,
                false,
                [],
                new GenerationFileTemplate(
                    $this->stubPath($descriptor->stub()),
                    $descriptor->replacements($moduleNamespace, $module),
                ),
            );
        }

        return new GenerationPlan($targets);
    }

    private function rootPath(): string
    {
        $rootPath = rtrim($this->configuration->path(), '/\\');

        if ($rootPath === '') {
            return DIRECTORY_SEPARATOR;
        }

        if (preg_match('/\A[A-Za-z]:\z/D', $rootPath) === 1) {
            return $rootPath.DIRECTORY_SEPARATOR;
        }

        return $rootPath;
    }

    private function stubPath(string $stub): string
    {
        $customPath = $this->application->basePath('stubs/'.$stub);

        return is_file($customPath)
            ? $customPath
            : dirname(__DIR__, 2).'/stubs/'.$stub;
    }
}
