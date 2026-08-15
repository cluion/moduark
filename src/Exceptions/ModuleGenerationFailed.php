<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleGenerationFailed extends RuntimeException
{
    public static function invalidName(string $name): self
    {
        return new self("Module name [{$name}] must be StudlyCase and contain only ASCII letters and numbers.");
    }

    public static function reservedName(string $name): self
    {
        return new self("Module name [{$name}] is reserved by PHP.");
    }

    public static function namespaceNotResolvable(string $path): self
    {
        return new self("Module path [{$path}] is not inside a registered Composer PSR-4 path.");
    }

    /**
     * @param list<string> $namespaces
     */
    public static function ambiguousNamespace(string $path, array $namespaces): self
    {
        return new self(sprintf(
            'Module path [%s] matches multiple Composer PSR-4 namespaces: [%s].',
            $path,
            implode('], [', $namespaces),
        ));
    }

    public static function alreadyExists(string $path): self
    {
        return new self("Module entry file [{$path}] already exists.");
    }

    public static function unreadableStub(string $path): self
    {
        return new self("Unable to read Module stub [{$path}].");
    }

    public static function directoryCreationFailed(string $path): self
    {
        return new self("Unable to create Module directory [{$path}].");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Unable to write Module entry file [{$path}].");
    }
}
