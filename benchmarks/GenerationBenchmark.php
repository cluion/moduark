<?php

declare(strict_types=1);

namespace Benchmarks;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\ModuleNamespaceResolver;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Generation\ModuleScaffoldPreset;
use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use RuntimeException;

final class GenerationBenchmark
{
    /**
     * @return array{
     *     fixture: 'full-scaffold',
     *     modules: int,
     *     targets_per_module: int,
     *     targets: int,
     *     warmups: int,
     *     iterations: int,
     *     samples: list<array{
     *         planning_ms: float,
     *         preflight_ms: float,
     *         execution_ms: float,
     *         total_ms: float,
     *         targets_per_second: float,
     *         verified_targets: int,
     *         collisions: 0,
     *         artisan_delegates: 0
     *     }>,
     *     summary: array{
     *         planning_ms: array{min: float, median: float, max: float},
     *         preflight_ms: array{min: float, median: float, max: float},
     *         execution_ms: array{min: float, median: float, max: float},
     *         total_ms: array{min: float, median: float, max: float},
     *         targets_per_second: array{min: float, median: float, max: float}
     *     }
     * }
     */
    public function run(int $modules, int $warmups, int $iterations): array
    {
        $this->validate($modules, $warmups, $iterations);
        $targetsPerModule = count(ModuleScaffoldPreset::Full->descriptors());

        for ($iteration = 0; $iteration < $warmups; $iteration++) {
            $this->sample($modules, $targetsPerModule);
        }

        $samples = [];

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $samples[] = $this->sample($modules, $targetsPerModule);
        }

        return [
            'fixture' => 'full-scaffold',
            'modules' => $modules,
            'targets_per_module' => $targetsPerModule,
            'targets' => $modules * $targetsPerModule,
            'warmups' => $warmups,
            'iterations' => $iterations,
            'samples' => $samples,
            'summary' => [
                'planning_ms' => $this->summary($samples, 'planning_ms'),
                'preflight_ms' => $this->summary($samples, 'preflight_ms'),
                'execution_ms' => $this->summary($samples, 'execution_ms'),
                'total_ms' => $this->summary($samples, 'total_ms'),
                'targets_per_second' => $this->summary($samples, 'targets_per_second'),
            ],
        ];
    }

    private function validate(int $modules, int $warmups, int $iterations): void
    {
        if ($modules < 1) {
            throw new RuntimeException('Generation benchmark modules must be at least 1.');
        }

        if ($warmups < 0) {
            throw new RuntimeException('Generation benchmark warmups cannot be negative.');
        }

        if ($iterations < 1) {
            throw new RuntimeException('Generation benchmark iterations must be at least 1.');
        }
    }

    /**
     * @return array{
     *     planning_ms: float,
     *     preflight_ms: float,
     *     execution_ms: float,
     *     total_ms: float,
     *     targets_per_second: float,
     *     verified_targets: int,
     *     collisions: 0,
     *     artisan_delegates: 0
     * }
     */
    private function sample(int $modules, int $targetsPerModule): array
    {
        $token = strtoupper(bin2hex(random_bytes(8)));
        $root = sys_get_temp_dir().'/moduark-generation-benchmark-'.$token;
        $applicationPath = $root.'/application';
        $appPath = $applicationPath.'/app';
        $modulePath = $appPath.'/Modules';
        $namespace = 'GenerationBenchmark\\Fixture'.$token;
        $filesystem = new Filesystem;
        $previousContainer = Container::getInstance();

        if (! mkdir($appPath, 0755, true) && ! is_dir($appPath)) {
            throw new RuntimeException("Unable to create generation benchmark root [{$appPath}].");
        }

        $loader = new ClassLoader($root.'/vendor');
        $loader->addPsr4($namespace.'\\', $appPath);
        $loader->register(true);

        try {
            $configuration = ModulesConfig::from([
                'path' => $modulePath,
                'architecture' => [
                    'level' => 1,
                    'rules' => [],
                ],
            ], []);
            $planner = new ModuleScaffoldPlanner(
                new Application($applicationPath),
                $configuration,
                new ModuleNamespaceResolver,
            );
            $preflight = new GenerationPreflight;
            $executor = new GenerationExecutor($filesystem);
            $plans = [];
            $plannedTargets = [];

            $started = hrtime(true);

            for ($module = 1; $module <= $modules; $module++) {
                $plan = $planner->plan(sprintf('Module%04d', $module), ModuleScaffoldPreset::Full);
                $plans[] = $plan;

                foreach ($plan->targets() as $target) {
                    $plannedTargets[] = $target;
                }
            }

            $planned = hrtime(true);

            foreach ($plans as $plan) {
                if ($preflight->collisions($plan) !== []) {
                    throw new RuntimeException('Generated benchmark plans must have zero collisions.');
                }
            }

            $preflighted = hrtime(true);

            foreach ($plans as $plan) {
                $result = $executor->execute(
                    $plan,
                    static function (GenerationTarget $target): int {
                        throw new RuntimeException(
                            "Generation benchmark target [{$target->moduleRelativePath()}] unexpectedly requested Artisan delegation.",
                        );
                    },
                );

                if (! $result->successful()) {
                    throw new RuntimeException(
                        'Generation benchmark execution failed: '.($result->failureMessage() ?? 'unknown failure'),
                    );
                }
            }

            $executed = hrtime(true);
            $expectedTargets = $modules * $targetsPerModule;

            if (count($plans) !== $modules || count($plannedTargets) !== $expectedTargets) {
                throw new RuntimeException('Generation benchmark produced an incomplete plan inventory.');
            }

            $paths = [];

            foreach ($plannedTargets as $target) {
                $path = $target->filePath();

                if (! is_file($path) || is_link($path)) {
                    throw new RuntimeException(
                        "Generation benchmark target [{$target->moduleRelativePath()}] was not written as a regular file.",
                    );
                }

                $paths[$path] = true;
            }

            if (count($paths) !== $expectedTargets) {
                throw new RuntimeException('Generation benchmark target paths must be unique.');
            }

            $planningMs = $this->milliseconds($started, $planned);
            $preflightMs = $this->milliseconds($planned, $preflighted);
            $executionMs = $this->milliseconds($preflighted, $executed);
            $totalMs = $this->milliseconds($started, $executed);

            return [
                'planning_ms' => $planningMs,
                'preflight_ms' => $preflightMs,
                'execution_ms' => $executionMs,
                'total_ms' => $totalMs,
                'targets_per_second' => round($expectedTargets / max($totalMs / 1000, 0.000_001), 3),
                'verified_targets' => count($paths),
                'collisions' => 0,
                'artisan_delegates' => 0,
            ];
        } finally {
            $loader->unregister();
            Container::setInstance($previousContainer);

            if (is_dir($root) && ! $filesystem->deleteDirectory($root)) {
                throw new RuntimeException("Unable to remove generation benchmark root [{$root}].");
            }
        }
    }

    private function milliseconds(int $start, int $end): float
    {
        return round(($end - $start) / 1_000_000, 3);
    }

    /**
     * @param list<array{
     *     planning_ms: float,
     *     preflight_ms: float,
     *     execution_ms: float,
     *     total_ms: float,
     *     targets_per_second: float,
     *     verified_targets: int,
     *     collisions: 0,
     *     artisan_delegates: 0
     * }> $samples
     * @param 'planning_ms'|'preflight_ms'|'execution_ms'|'total_ms'|'targets_per_second' $metric
     * @return array{min: float, median: float, max: float}
     */
    private function summary(array $samples, string $metric): array
    {
        $values = array_map(
            static fn (array $sample): float => $sample[$metric],
            $samples,
        );
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        return [
            'min' => $values[0],
            'median' => round($median, 3),
            'max' => $values[$count - 1],
        ];
    }
}
