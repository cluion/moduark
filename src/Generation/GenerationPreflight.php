<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationPreflight
{
    /** @return list<GenerationTarget> */
    public function collisions(GenerationPlan $plan): array
    {
        $collisions = [];
        $seen = [];

        foreach ($plan->targets() as $target) {
            $path = $this->canonicalPath($target->filePath());
            $key = DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;

            if (isset($seen[$key]) || (is_file($path) && ! $target->overwrite())) {
                $collisions[$key] = $target;
            }

            $seen[$key] = true;
        }

        ksort($collisions, SORT_STRING);

        return array_values($collisions);
    }

    private function canonicalPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return strlen($path) > 1 ? rtrim($path, '/') : $path;
    }
}
