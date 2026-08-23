<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

final readonly class ModuleAssetManifest
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private ResourceManifest $resources)
    {
    }

    /** @return list<string> */
    public function inputs(): array
    {
        $inputs = [];

        foreach ($this->resources->all() as $resource) {
            if ($resource->plugin() !== 'assets'
                || ($resource->attributes()['type'] ?? 'input') !== 'input'
                || $resource->sourcePath() === null) {
                continue;
            }

            $inputs[] = $resource->sourcePath();
        }

        sort($inputs, SORT_STRING);

        return $inputs;
    }

    /**
     * @return array{schema_version: int, modules: list<string>, inputs: list<string>}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'modules' => $this->resources->moduleClasses(),
            'inputs' => $this->inputs(),
        ];
    }
}
