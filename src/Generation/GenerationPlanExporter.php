<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final class GenerationPlanExporter
{
    public const SCHEMA_VERSION = 1;

    /** @return list<string> */
    public function textLines(GenerationPlan $plan): array
    {
        return array_map(
            fn (GenerationTarget $target): string => sprintf(
                '%s %s',
                strtoupper($this->operation($target)),
                $target->moduleRelativePath(),
            ),
            $plan->targets(),
        );
    }

    /** @param list<GenerationTarget> $collisions */
    public function json(
        GenerationPlanOutputContext $context,
        GenerationPlan $plan,
        array $collisions = [],
    ): string {
        $collisionPaths = [];

        foreach ($collisions as $collision) {
            $collisionPaths[$collision->filePath()] = true;
        }

        $targets = array_map(
            fn (GenerationTarget $target): array => [
                'operation' => $this->operation($target),
                'generator_id' => $target->generatorId(),
                'path' => $target->moduleRelativePath(),
                'overwrite' => $target->overwrite(),
                'collision' => isset($collisionPaths[$target->filePath()]),
            ],
            $plan->targets(),
        );
        $overwrites = count(array_filter(
            $targets,
            static fn (array $target): bool => $target['operation'] === 'overwrite',
        ));

        return $this->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'status' => $collisions === [] ? 'planned' : 'collisions_found',
            'complete' => true,
            'exit_code' => $collisions === [] ? 0 : 1,
            'command' => $context->command(),
            'module' => $context->module(),
            'generator_id' => $context->generatorId(),
            'preset' => $context->preset(),
            'summary' => [
                'targets' => count($targets),
                'creates' => count($targets) - $overwrites,
                'overwrites' => $overwrites,
                'collisions' => count($collisions),
            ],
            'targets' => $targets,
            'error' => null,
        ]);
    }

    public function jsonFailure(
        GenerationPlanOutputContext $context,
        int $exitCode,
        string $code,
        string $message,
    ): string {
        return $this->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'incomplete',
            'complete' => false,
            'exit_code' => $exitCode,
            'command' => $context->command(),
            'module' => $context->module(),
            'generator_id' => $context->generatorId(),
            'preset' => $context->preset(),
            'summary' => [
                'targets' => 0,
                'creates' => 0,
                'overwrites' => 0,
                'collisions' => 0,
            ],
            'targets' => [],
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    private function operation(GenerationTarget $target): string
    {
        return $target->overwrite()
            && (file_exists($target->filePath()) || is_link($target->filePath()))
                ? 'overwrite'
                : 'create';
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
