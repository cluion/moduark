<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Illuminate\Contracts\Foundation\Application;

final readonly class ModuleGenerator
{
    public function __construct(
        private Application $application,
        private ModulesConfig $configuration,
        private ModuleNamespaceResolver $namespaceResolver,
    ) {
    }

    public function generate(string $rawName): string
    {
        $name = ModuleName::from($rawName);
        $rootPath = rtrim($this->configuration->path(), '/\\');

        if ($rootPath === '') {
            $rootPath = DIRECTORY_SEPARATOR;
        } elseif (preg_match('/\A[A-Za-z]:\z/D', $rootPath) === 1) {
            $rootPath .= DIRECTORY_SEPARATOR;
        }

        $rootNamespace = $this->namespaceResolver->resolve($rootPath);
        $directory = $rootPath.DIRECTORY_SEPARATOR.$name->value();
        $path = $directory.DIRECTORY_SEPARATOR.$name->entryClass().'.php';

        if (file_exists($path)) {
            throw ModuleGenerationFailed::alreadyExists($path);
        }

        $stubPath = $this->stubPath();
        $stub = @file_get_contents($stubPath);

        if ($stub === false) {
            throw ModuleGenerationFailed::unreadableStub($stubPath);
        }

        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$rootNamespace.'\\'.$name->value(), $name->entryClass()],
            $stub,
        );

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ModuleGenerationFailed::directoryCreationFailed($directory);
        }

        $handle = @fopen($path, 'x');

        if ($handle === false) {
            if (file_exists($path)) {
                throw ModuleGenerationFailed::alreadyExists($path);
            }

            throw ModuleGenerationFailed::writeFailed($path);
        }

        $written = 0;
        $length = strlen($contents);

        try {
            while ($written < $length) {
                $bytes = fwrite($handle, substr($contents, $written));

                if ($bytes === false || $bytes === 0) {
                    throw ModuleGenerationFailed::writeFailed($path);
                }

                $written += $bytes;
            }
        } catch (ModuleGenerationFailed $exception) {
            fclose($handle);
            @unlink($path);

            throw $exception;
        }

        fclose($handle);

        return $path;
    }

    private function stubPath(): string
    {
        $customPath = $this->application->basePath('stubs/module.stub');

        return is_file($customPath)
            ? $customPath
            : dirname(__DIR__, 2).'/stubs/module.stub';
    }
}
