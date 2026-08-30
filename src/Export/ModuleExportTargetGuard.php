<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use RuntimeException;

final readonly class ModuleExportTargetGuard
{
    private string $base;

    public function __construct(string $applicationBasePath)
    {
        $this->base = rtrim(str_replace('\\', '/', $applicationBasePath), '/');
    }

    public function absoluteTarget(ModuleExportPlan $plan): string
    {
        return $this->base.'/'.$plan->target();
    }

    public function assertAvailable(string $target): void
    {
        $cursor = str_replace('\\', '/', $target);

        if (! str_starts_with($cursor, $this->base.'/')) {
            throw new RuntimeException('The export target escaped the application root.');
        }

        if (file_exists($cursor) || is_link($cursor)) {
            throw new RuntimeException("The export target [{$cursor}] already exists.");
        }

        while ($cursor !== $this->base && str_starts_with($cursor, $this->base.'/')) {
            if (is_link($cursor)) {
                throw new RuntimeException("The export target ancestor [{$cursor}] is a symbolic link.");
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                break;
            }

            $cursor = str_replace('\\', '/', $parent);
        }
    }

    /** @return list<string> */
    public function missingParents(string $parent): array
    {
        $missing = [];
        $cursor = str_replace('\\', '/', $parent);

        while ($cursor !== $this->base
            && str_starts_with($cursor, $this->base.'/')
            && ! is_dir($cursor)) {
            $missing[] = $cursor;
            $next = dirname($cursor);

            if ($next === $cursor) {
                break;
            }

            $cursor = str_replace('\\', '/', $next);
        }

        return $missing;
    }
}
