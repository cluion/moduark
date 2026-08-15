<?php

declare(strict_types=1);

namespace Benchmarks;

use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ArchitectureBenchmark
{
    /**
     * @return array{
     *     modules: int,
     *     php_files: int,
     *     files_per_module: int,
     *     warmups: int,
     *     iterations: int,
     *     rules: int,
     *     samples: list<array{discovery_ms: float, check_ms: float, total_ms: float}>,
     *     summary: array{
     *         discovery_ms: array{min: float, median: float, max: float},
     *         check_ms: array{min: float, median: float, max: float},
     *         total_ms: array{min: float, median: float, max: float}
     *     }
     * }
     */
    public function run(
        int $modules,
        int $filesPerModule,
        int $warmups,
        int $iterations,
    ): array {
        $this->validate($modules, $filesPerModule, $warmups, $iterations);

        $token = bin2hex(random_bytes(8));
        $root = sys_get_temp_dir().'/moduark-benchmark-'.$token.'/Modules';
        $namespace = 'ModuarkBenchmark\\Fixture'.$token.'\\Modules';
        $loader = $this->autoloader($root, $namespace);

        spl_autoload_register($loader);

        try {
            $this->generateFixture($root, $namespace, $modules, $filesPerModule);

            for ($iteration = 0; $iteration < $warmups; $iteration++) {
                $this->sample($root);
            }

            $samples = [];

            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                $samples[] = $this->sample($root);
            }

            return [
                'modules' => $modules,
                'php_files' => $modules * $filesPerModule,
                'files_per_module' => $filesPerModule,
                'warmups' => $warmups,
                'iterations' => $iterations,
                'rules' => 6,
                'samples' => $samples,
                'summary' => [
                    'discovery_ms' => $this->summary($samples, 'discovery_ms'),
                    'check_ms' => $this->summary($samples, 'check_ms'),
                    'total_ms' => $this->summary($samples, 'total_ms'),
                ],
            ];
        } finally {
            spl_autoload_unregister($loader);
            $this->deleteDirectory(dirname($root));
        }
    }

    private function validate(
        int $modules,
        int $filesPerModule,
        int $warmups,
        int $iterations,
    ): void {
        if ($modules < 1) {
            throw new RuntimeException('Benchmark modules must be at least 1.');
        }

        if ($filesPerModule < 2) {
            throw new RuntimeException('Benchmark files per Module must be at least 2.');
        }

        if ($warmups < 0) {
            throw new RuntimeException('Benchmark warmups cannot be negative.');
        }

        if ($iterations < 1) {
            throw new RuntimeException('Benchmark iterations must be at least 1.');
        }
    }

    /**
     * @return callable(string): void
     */
    private function autoloader(string $root, string $namespace): callable
    {
        $prefix = $namespace.'\\';

        return static function (string $class) use ($prefix, $root): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relativePath = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            $path = $root.'/'.$relativePath;

            if (is_file($path)) {
                require $path;
            }
        };
    }

    private function generateFixture(
        string $root,
        string $namespace,
        int $modules,
        int $filesPerModule,
    ): void {
        for ($index = 1; $index <= $modules; $index++) {
            $name = sprintf('M%04d', $index);
            $moduleNamespace = $namespace.'\\'.$name;
            $modulePath = $root.'/'.$name;
            $contract = $moduleNamespace.'\\Contracts\\PublicContract';

            $this->writeFile(
                $modulePath.'/'.$name.'Module.php',
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$moduleNamespace};\n\nfinal class {$name}Module extends \\Cluion\\Moduark\\Module\n{\n}\n",
            );
            $this->writeFile(
                $modulePath.'/Contracts/PublicContract.php',
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$moduleNamespace}\\Contracts;\n\ninterface PublicContract\n{\n}\n",
            );

            for ($file = 1; $file <= $filesPerModule - 2; $file++) {
                $symbol = sprintf('Symbol%04d', $file);
                $this->writeFile(
                    $modulePath.'/Internal/'.$symbol.'.php',
                    "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$moduleNamespace}\\Internal;\n\nfinal class {$symbol}\n{\n    public function accept(\\{$contract} \$contract): \\{$contract}\n    {\n        return \$contract;\n    }\n}\n",
                );
            }
        }
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create benchmark directory [{$directory}].");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write benchmark fixture [{$path}].");
        }
    }

    /**
     * @return array{discovery_ms: float, check_ms: float, total_ms: float}
     */
    private function sample(string $root): array
    {
        $started = hrtime(true);
        $registry = (new ModuleDiscoverer)->discover($root);
        $discovered = hrtime(true);
        $report = $this->checker($root, $registry)->check(Level::Modular);
        $checked = hrtime(true);

        if (! $report->complete() || $report->violations() !== []) {
            throw new RuntimeException('Generated benchmark fixture must pass a complete Level 1 check.');
        }

        return [
            'discovery_ms' => $this->milliseconds($started, $discovered),
            'check_ms' => $this->milliseconds($discovered, $checked),
            'total_ms' => $this->milliseconds($started, $checked),
        ];
    }

    private function checker(string $root, ModuleRegistry $registry): ArchitectureChecker
    {
        $configuration = ModulesConfig::from([
            'path' => $root,
            'architecture' => [
                'level' => Level::Modular->value,
                'rules' => [],
            ],
        ], []);
        $publicApi = new ConventionPublicApi;

        return new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $configuration,
            new RuleResolver(new RulePresets),
            new RuleRunner([
                new ValidModuleStructureRule,
                new UniqueModuleIdentityRule,
                new MissingDependenciesRule,
                new UndeclaredDependenciesRule,
                new CyclesRule,
                new InternalApiAccessRule($publicApi),
            ]),
        );
    }

    private function milliseconds(int $start, int $end): float
    {
        return round(($end - $start) / 1_000_000, 3);
    }

    /**
     * @param list<array{discovery_ms: float, check_ms: float, total_ms: float}> $samples
     * @param 'discovery_ms'|'check_ms'|'total_ms' $metric
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

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($path);
    }
}
