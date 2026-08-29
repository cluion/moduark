<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Exceptions\ModuleActivationMutationFailed;
use Cluion\Moduark\Registry\ModuleRegistry;
use JsonException;

final readonly class FileModuleActivationStore implements ModuleActivationStore
{
    public const STANDALONE_SCHEMA_VERSION = 1;

    public function __construct(
        private string $statePath,
        private string $lockPath,
        private ModuleActivationDriver $driver,
        private AtomicFileWriter $writer,
    ) {
        if ($this->statePath === '' || $this->lockPath === '') {
            throw ModuleActivationMutationFailed::invalidState($this->statePath);
        }
    }

    public function path(): string
    {
        return $this->statePath;
    }

    public function load(array $knownNames): ModuleActivationSet
    {
        if (! is_file($this->statePath)) {
            return $this->driver === ModuleActivationDriver::Standalone
                ? ModuleActivationSet::all()
                : ModuleActivationSet::fromStates(array_fill_keys($knownNames, false));
        }

        $contents = file_get_contents($this->statePath);

        if ($contents === false) {
            throw ModuleActivationMutationFailed::invalidState($this->statePath);
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ModuleActivationMutationFailed::invalidState($this->statePath, $exception);
        }

        if (! is_array($payload)) {
            throw ModuleActivationMutationFailed::invalidState($this->statePath);
        }

        $states = $this->driver === ModuleActivationDriver::Standalone
            ? $this->standaloneStates($payload)
            : $payload;

        foreach ($states as $name => $enabled) {
            if (! is_string($name) || $name === '' || ! is_bool($enabled)) {
                throw ModuleActivationMutationFailed::invalidState($this->statePath);
            }
        }

        $normalized = [];

        foreach ($knownNames as $name) {
            $normalized[$name] = array_key_exists($name, $states)
                ? $states[$name]
                : $this->driver === ModuleActivationDriver::Standalone;
        }

        return ModuleActivationSet::fromStates($normalized);
    }

    public function commit(
        ModuleRegistry $inventory,
        ModuleActivationSet $expected,
        ModuleActivationPlan $plan,
        callable $beforeCommit,
    ): void {
        $directory = dirname($this->lockPath);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw ModuleActivationMutationFailed::directory($directory);
        }

        $lock = @fopen($this->lockPath, 'c+');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw ModuleActivationMutationFailed::lock($this->statePath);
        }

        try {
            $knownNames = array_map(
                static fn (DiscoveredModule $module): string => $module->name(),
                $inventory->all(),
            );
            $current = $this->load($knownNames);

            if ($current->fingerprint() !== $expected->fingerprint()) {
                throw ModuleActivationMutationFailed::concurrentChange($this->statePath);
            }

            $beforeCommit();
            $this->writer->write($this->statePath, $this->encode($knownNames, $plan->after()));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    private function standaloneStates(array $payload): array
    {
        if (($payload['schema_version'] ?? null) !== self::STANDALONE_SCHEMA_VERSION
            || ! isset($payload['modules'])
            || ! is_array($payload['modules'])
            || count($payload) !== 2) {
            throw ModuleActivationMutationFailed::invalidState($this->statePath);
        }

        return $payload['modules'];
    }

    /**
     * @param list<string> $knownNames
     * @param list<string> $activeNames
     */
    private function encode(array $knownNames, array $activeNames): string
    {
        $active = array_fill_keys($activeNames, true);
        $states = [];

        foreach ($knownNames as $name) {
            $states[$name] = isset($active[$name]);
        }

        ksort($states, SORT_STRING);
        $payload = $this->driver === ModuleActivationDriver::Standalone
            ? [
                'schema_version' => self::STANDALONE_SCHEMA_VERSION,
                'modules' => $states,
            ]
            : $states;

        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw ModuleActivationMutationFailed::write($this->statePath, $exception);
        }
    }
}
