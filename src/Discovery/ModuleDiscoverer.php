<?php

declare(strict_types=1);

namespace Cluion\Moduark\Discovery;

use Cluion\Moduark\Exceptions\ModuleDiscoveryFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use ParseError;
use ReflectionClass;

final class ModuleDiscoverer
{
    public function discover(
        string $rootPath,
        ?ModuleActivationSet $activationSet = null,
    ): ModuleRegistry
    {
        $activationSet ??= ModuleActivationSet::all();

        if (! is_dir($rootPath)) {
            return new ModuleRegistry([]);
        }

        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $rootPath = $rootPath === '' ? DIRECTORY_SEPARATOR : $rootPath;
        $files = [];

        foreach (['', 'app'] as $entryDirectory) {
            $pattern = $rootPath.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR;

            if ($entryDirectory !== '') {
                $pattern .= $entryDirectory.DIRECTORY_SEPARATOR;
            }

            $matches = glob($pattern.'*Module.php');

            if ($matches === false) {
                throw ModuleDiscoveryFailed::scanFailed($rootPath);
            }

            foreach ($matches as $match) {
                if (is_file($match) && $activationSet->includes($this->moduleName($match))) {
                    $files[$match] = $match;
                }
            }
        }

        $files = array_values($files);
        sort($files, SORT_STRING);

        return new ModuleRegistry(array_map(
            fn (string $path): DiscoveredModule => $this->inspect($path),
            $files,
        ));
    }

    private function moduleName(string $path): string
    {
        $entryDirectory = dirname($path);
        $moduleDirectory = basename($entryDirectory) === 'app'
            ? dirname($entryDirectory)
            : $entryDirectory;

        return basename($moduleDirectory);
    }

    private function inspect(string $path): DiscoveredModule
    {
        $moduleName = $this->moduleName($path);
        $expectedClassName = $moduleName.'Module';
        $expectedFileName = $expectedClassName.'.php';

        if (basename($path) !== $expectedFileName) {
            throw ModuleDiscoveryFailed::invalidFileName($path, $expectedFileName);
        }

        [$namespace, $className] = $this->declaration($path);

        if ($className !== $expectedClassName) {
            throw ModuleDiscoveryFailed::invalidClassName($path, $expectedClassName, $className);
        }

        $namespaceParts = explode('\\', $namespace);
        $namespaceTail = end($namespaceParts);

        if ($namespaceTail !== $moduleName) {
            throw ModuleDiscoveryFailed::invalidNamespace(
                $path,
                $moduleName,
                $namespaceTail,
            );
        }

        $moduleClass = $namespace.'\\'.$className;

        if (! class_exists($moduleClass)) {
            throw ModuleDiscoveryFailed::classNotAutoloadable($moduleClass, $path);
        }

        $reflection = new ReflectionClass($moduleClass);

        if (! is_a($moduleClass, Module::class, true) || ! $reflection->isInstantiable()) {
            throw ModuleDiscoveryFailed::invalidModuleClass($moduleClass, $path);
        }

        $autoloadedPath = $reflection->getFileName();
        $expectedRealPath = realpath($path);
        $autoloadedRealPath = is_string($autoloadedPath) ? realpath($autoloadedPath) : false;

        if ($expectedRealPath === false || $autoloadedRealPath !== $expectedRealPath) {
            throw ModuleDiscoveryFailed::sourceMismatch(
                $moduleClass,
                $path,
                is_string($autoloadedPath) ? $autoloadedPath : '[internal]',
            );
        }

        return new DiscoveredModule($moduleName, $moduleClass, $path, $namespace);
    }

    /**
     * @return array{string, string}
     */
    private function declaration(string $path): array
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw ModuleDiscoveryFailed::unreadableFile($path);
        }

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $exception) {
            throw ModuleDiscoveryFailed::invalidSyntax($path, $exception->getMessage());
        }

        $namespace = '';
        $className = null;
        $previousSignificant = null;

        foreach ($tokens as $index => $token) {
            if (is_string($token)) {
                if (trim($token) !== '') {
                    $previousSignificant = $token;
                }

                continue;
            }

            [$id] = $token;

            if ($id === T_NAMESPACE) {
                $namespace = $this->namespaceAt($tokens, $index + 1);
            }

            if ($id === T_CLASS && $previousSignificant !== T_NEW && $previousSignificant !== T_DOUBLE_COLON) {
                $className = $this->classNameAt($tokens, $index + 1);

                if ($className !== null) {
                    break;
                }
            }

            if (! in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $previousSignificant = $id;
            }
        }

        if ($className === null) {
            throw ModuleDiscoveryFailed::missingClass($path);
        }

        return [trim($namespace, '\\'), $className];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function namespaceAt(array $tokens, int $start): string
    {
        $namespace = '';

        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $namespace .= $token[1];
            }
        }

        return $namespace;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function classNameAt(array $tokens, int $start): ?string
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (is_string($token) && $token === '{') {
                return null;
            }
        }

        return null;
    }
}
