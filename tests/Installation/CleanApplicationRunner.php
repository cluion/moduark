<?php

declare(strict_types=1);

namespace Tests\Installation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\Distribution\PackageArchiveContract;

final class CleanApplicationRunner
{
    private string $packagePath;

    private ?string $packageVersion;

    public function __construct(
        string $packagePath,
        private readonly bool $keep = false,
        ?string $packageVersion = null,
        private readonly bool $withBoost = false,
    ) {
        $resolved = realpath($packagePath);

        if ($resolved === false || ! is_file($resolved.'/composer.json')) {
            throw new RuntimeException("Moduark package path [{$packagePath}] is invalid.");
        }

        $this->packagePath = $resolved;
        $this->packageVersion = $packageVersion === null
            ? null
            : self::parsePackageVersion($packageVersion);
    }

    /**
     * @return list<int>
     */
    public static function parseMajors(mixed $value): array
    {
        $value = $value === false ? '12,13' : $value;

        if (! is_string($value) || preg_match('/\A(?:12|13)(?:,(?:12|13))*\z/', $value) !== 1) {
            throw new RuntimeException('The --laravel option must contain 12, 13, or 12,13.');
        }

        $majors = [];

        foreach (explode(',', $value) as $major) {
            $majors[(int) $major] = (int) $major;
        }

        return array_values($majors);
    }

    public static function parsePackageVersion(mixed $value): ?string
    {
        if ($value === false) {
            return null;
        }

        if (
            ! is_string($value)
            || preg_match(
                '/\A(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'The --package option must be an exact stable or pre-release version.',
            );
        }

        return $value;
    }

    /**
     * @param list<int> $majors
     * @return list<array{major: int, version: string}>
     */
    public function run(array $majors): array
    {
        if ($majors === []) {
            throw new RuntimeException('At least one Laravel major is required.');
        }

        foreach ($majors as $major) {
            if (! in_array($major, [12, 13], true)) {
                throw new RuntimeException("Laravel major [{$major}] is outside the installation matrix.");
            }
        }

        $root = sys_get_temp_dir().'/moduark-installation-'.bin2hex(random_bytes(8));

        if (! mkdir($root, 0755, true)) {
            throw new RuntimeException("Unable to create installation root [{$root}].");
        }

        $environment = getenv();
        $environment['COMPOSER_HOME'] = $root.'/composer-home';
        $environment['COMPOSER_CACHE_DIR'] = $root.'/composer-cache';
        $environment['COMPOSER_NO_INTERACTION'] = '1';
        $environment['COMPOSER_PROCESS_TIMEOUT'] = '900';
        $results = [];

        echo "Clean installation root: {$root}\n";
        echo $this->packageVersion === null
            ? "Package source: current checkout as cluion/moduark:dev-main\n"
            : "Package source: Packagist cluion/moduark:{$this->packageVersion}\n";
        echo $this->withBoost
            ? "Laravel Boost Skill installation: enabled\n"
            : "Laravel Boost Skill installation: disabled\n";

        try {
            foreach ($majors as $major) {
                $results[] = $this->runMajor($root, $major, $environment);
            }

            return $results;
        } finally {
            if ($this->keep) {
                echo "Preserved installation root: {$root}\n";
            } else {
                $this->deleteDirectory($root);
                echo "Removed installation root: {$root}\n";
            }
        }
    }

    /**
     * @param array<string, string> $environment
     * @return array{major: int, version: string}
     */
    private function runMajor(string $root, int $major, array $environment): array
    {
        $application = $root.'/laravel-'.$major;

        echo "\n== Laravel {$major} clean application ==\n";
        $this->command([
            'composer',
            'create-project',
            "laravel/laravel:^{$major}.0",
            $application,
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $root, $environment);
        $this->assertFileMissing(
            $application.'/config/moduark.php',
            'The clean Laravel application unexpectedly contains config/moduark.php.',
        );

        $packageConstraint = $this->packageVersion ?? 'dev-main';

        if ($this->packageVersion === null) {
            $repository = json_encode([
                'type' => 'path',
                'url' => $this->packagePath,
                'options' => [
                    'versions' => [
                        'cluion/moduark' => 'dev-main',
                    ],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            $this->command([
                'composer',
                'config',
                '--json',
                'repositories.moduark',
                $repository,
            ], $application, $environment);
        }

        $this->command([
            'composer',
            'require',
            'cluion/moduark:'.$packageConstraint,
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $application, $environment);
        $this->assertFileMissing(
            $application.'/config/moduark.php',
            'Installing Moduark must not publish config/moduark.php.',
        );

        if ($this->packageVersion !== null) {
            $this->assertPublishedDistribution($application.'/vendor/cluion/moduark');
        }

        $commands = $this->artisan($application, ['list', '--raw'], $environment);

        foreach (
            [
                'moduark:make-module',
                'moduark:baseline',
                'moduark:cache',
                'moduark:check',
                'moduark:clear',
                'moduark:graph',
                'moduark:inspect',
                'moduark:list',
                'moduark:make',
            ] as $command
        ) {
            $this->assertMatches(
                '/^'.preg_quote($command, '/').'\b/m',
                $commands,
                "Package auto-discovery did not register [{$command}].",
            );
        }

        if ($this->withBoost) {
            $this->installAndVerifyBoostSkill($application, $environment);
        }

        $versionOutput = $this->artisan($application, ['--version'], $environment);
        if (preg_match('/Laravel Framework ([^\s]+)/', $versionOutput, $versionMatch) !== 1) {
            throw new RuntimeException('Unable to determine the installed Laravel framework version.');
        }

        $version = $versionMatch[1];

        $this->artisan($application, ['moduark:make-module', 'User'], $environment);
        $modulePath = $application.'/app/Modules/User/UserModule.php';
        $this->assertFileExists($modulePath, 'moduark:make-module did not create UserModule.php.');
        $this->assertOnlyGeneratedModuleFile($application.'/app/Modules/User', $modulePath);

        foreach ([
            'Storefront' => ['preset' => 'web', 'target' => 'Tests/Feature/Web/StorefrontWebTest.php'],
            'Gateway' => ['preset' => 'api', 'target' => 'Tests/Feature/Api/GatewayApiTest.php'],
            'Core' => ['preset' => 'domain', 'target' => 'Infrastructure/.gitkeep'],
        ] as $scaffoldModule => $expectation) {
            $this->artisan(
                $application,
                ['moduark:make-module', $scaffoldModule, '--preset='.$expectation['preset']],
                $environment,
            );
            $this->assertFileExists(
                $application.'/app/Modules/'.$scaffoldModule.'/'.$expectation['target'],
                "The {$expectation['preset']} scaffold did not create its representative target.",
            );
        }

        $scaffoldDryRun = $this->artisan(
            $application,
            ['moduark:make-module', 'Catalog', '--preset=full', '--dry-run'],
            $environment,
        );

        foreach ([
            'CREATE CatalogModule.php',
            'CREATE routes/web.php',
            'CREATE Http/Controllers/Api/CatalogController.php',
            'CREATE Tests/Feature/Api/CatalogApiTest.php',
            'CREATE Infrastructure/.gitkeep',
        ] as $plannedTarget) {
            $this->assertContains(
                $plannedTarget,
                $scaffoldDryRun,
                "moduark:make-module full dry-run omitted [{$plannedTarget}].",
            );
        }

        $this->assertFileMissing(
            $application.'/app/Modules/Catalog/CatalogModule.php',
            'moduark:make-module full dry-run mutated the application.',
        );
        $this->artisan(
            $application,
            ['moduark:make-module', 'Catalog', '--preset=full'],
            $environment,
        );

        $catalogRoot = $application.'/app/Modules/Catalog';
        foreach ([
            'CatalogModule.php',
            'routes/web.php',
            'routes/api.php',
            'Http/Controllers/Web/CatalogController.php',
            'Http/Controllers/Api/CatalogController.php',
            'Http/Requests/Api/CatalogRequest.php',
            'Http/Resources/Api/CatalogResource.php',
            'resources/views/index.blade.php',
            'resources/lang/en/messages.php',
            'Tests/Feature/Web/CatalogWebTest.php',
            'Tests/Feature/Api/CatalogApiTest.php',
            'Domain/.gitkeep',
            'Application/.gitkeep',
            'Infrastructure/.gitkeep',
        ] as $relativePath) {
            $this->assertFileExists(
                $catalogRoot.'/'.$relativePath,
                "Full scaffold did not create Module-owned target [{$relativePath}].",
            );
        }

        foreach ([
            $application.'/app/Modules/Storefront/Tests/Feature/Web/StorefrontWebTest.php',
            $application.'/app/Modules/Gateway/Tests/Feature/Api/GatewayApiTest.php',
            $catalogRoot.'/Tests/Feature/Web/CatalogWebTest.php',
            $catalogRoot.'/Tests/Feature/Api/CatalogApiTest.php',
        ] as $presetTestPath) {
            $this->command([
                $application.'/vendor/bin/phpunit',
                '--colors=never',
                $presetTestPath,
            ], $application, $environment);
        }

        $dryRun = $this->artisan(
            $application,
            [
                'moduark:make',
                'User',
                'model',
                'Profile',
                '--factory',
                '--migration',
                '--dry-run',
            ],
            $environment,
        );
        $this->assertContains(
            'CREATE Models/Profile.php',
            $dryRun,
            'moduark:make --dry-run did not render the resolved Module-relative target.',
        );
        $this->assertContains(
            'CREATE Database/Factories/ProfileFactory.php',
            $dryRun,
            'moduark:make --dry-run omitted the Module-owned factory target.',
        );
        $this->assertContains(
            'CREATE Database/Migrations/',
            $dryRun,
            'moduark:make --dry-run omitted the Module-owned migration target.',
        );
        $this->assertFileMissing(
            $application.'/app/Modules/User/Models/Profile.php',
            'moduark:make --dry-run must not create the planned model.',
        );
        $this->assertFileMissing(
            $application.'/app/Modules/User/Database/Factories/ProfileFactory.php',
            'moduark:make --dry-run must not create the planned factory.',
        );

        $this->artisan(
            $application,
            ['moduark:make', 'User', 'model', 'Profile', '--factory', '--migration'],
            $environment,
        );
        $this->assertFileExists(
            $application.'/app/Modules/User/Models/Profile.php',
            'moduark:make did not create the User Profile model.',
        );
        $this->assertFileExists(
            $application.'/app/Modules/User/Database/Factories/ProfileFactory.php',
            'moduark:make did not create the User Profile factory.',
        );
        $profileMigrations = glob(
            $application.'/app/Modules/User/Database/Migrations/*_create_profiles_table.php',
        );
        if (! is_array($profileMigrations) || count($profileMigrations) !== 1) {
            throw new RuntimeException(
                'moduark:make must create exactly one Module-owned Profile migration.',
            );
        }
        $this->artisan(
            $application,
            ['moduark:make', 'User', 'controller', 'ProfileController', '--invokable'],
            $environment,
        );
        $this->assertFileExists(
            $application.'/app/Modules/User/Http/Controllers/ProfileController.php',
            'moduark:make did not create the User ProfileController.',
        );

        foreach ([
            ['class', 'Support/InvokableTask', '--invokable', 'Support/InvokableTask.php'],
            ['cast', 'Money/AmountCast', '--inbound', 'Casts/Money/AmountCast.php'],
            ['channel', 'Billing/InvoiceChannel', null, 'Broadcasting/Billing/InvoiceChannel.php'],
            ['component', 'Billing/InvoiceCard', null, 'View/Components/Billing/InvoiceCard.php'],
            ['enum', 'Workflow/Status', '--string', 'Enums/Workflow/Status.php'],
            ['event', 'Billing/InvoicePaid', null, 'Events/Billing/InvoicePaid.php'],
            ['exception', 'Billing/PaymentFailed', '--render', 'Exceptions/Billing/PaymentFailed.php'],
            ['factory', 'Billing/InvoiceFactory', '--model=Profile', 'Database/Factories/Billing/InvoiceFactory.php'],
            ['interface', 'Lookup/UserLookup', null, 'Contracts/Lookup/UserLookup.php'],
            ['job', 'Billing/ProcessInvoice', null, 'Jobs/Billing/ProcessInvoice.php'],
            ['job', 'Billing/SyncInvoice', '--sync', 'Jobs/Billing/SyncInvoice.php'],
            ['job', 'Billing/ReconcileInvoices', '--batched', 'Jobs/Billing/ReconcileInvoices.php'],
            ['job-middleware', 'Billing/WithoutOverlappingInvoices', null, 'Jobs/Middleware/Billing/WithoutOverlappingInvoices.php'],
            ['listener', 'Billing/SendInvoiceReceipt', '--event=Billing/InvoicePaid', 'Listeners/Billing/SendInvoiceReceipt.php'],
            ['mail', 'Billing/InvoiceReceipt', null, 'Mail/Billing/InvoiceReceipt.php'],
            ['middleware', 'Admin/EnsureProfileIsComplete', null, 'Http/Middleware/Admin/EnsureProfileIsComplete.php'],
            ['notification', 'Billing/InvoicePaid', null, 'Notifications/Billing/InvoicePaid.php'],
            ['observer', 'Profile/ProfileObserver', '--model=Profile', 'Observers/Profile/ProfileObserver.php'],
            ['policy', 'Profile/ProfilePolicy', '--model=Profile', 'Policies/Profile/ProfilePolicy.php'],
            ['request', 'Profile/StoreProfileRequest', null, 'Http/Requests/Profile/StoreProfileRequest.php'],
            ['resource', 'Profile/ProfileResource', null, 'Http/Resources/Profile/ProfileResource.php'],
            ['resource', 'Profile/ProfileCollection', '--collection', 'Http/Resources/Profile/ProfileCollection.php'],
            ['resource', 'Profile/ProfileJsonApiResource', '--json-api', 'Http/Resources/Profile/ProfileJsonApiResource.php'],
            ['rule', 'Profile/RequiredProfile', '--implicit', 'Rules/Profile/RequiredProfile.php'],
            ['scope', 'Visibility/PublishedScope', null, 'Models/Scopes/Visibility/PublishedScope.php'],
            ['seeder', 'Billing/ProfileSeeder', null, 'Database/Seeders/Billing/ProfileSeeder.php'],
            ['test', 'Billing/ModuleFeatureTest', '--phpunit', 'Tests/Feature/Billing/ModuleFeatureTest.php'],
            ['test', 'Billing/ModuleUnitTest', '--unit', 'Tests/Unit/Billing/ModuleUnitTest.php'],
            ['test', 'Billing/ModulePestTest', '--pest', 'Tests/Feature/Billing/ModulePestTest.php'],
            ['trait', 'Serialization/SerializesAttributes', null, 'Concerns/Serialization/SerializesAttributes.php'],
            ['view', 'billing.invoice-summary', null, 'resources/views/billing/invoice-summary.blade.php'],
        ] as [$type, $name, $option, $relativePath]) {
            $arguments = ['moduark:make', 'User', $type, $name];

            if ($option !== null) {
                $arguments[] = $option;
            }

            $dryRun = $this->artisan(
                $application,
                [...$arguments, '--dry-run'],
                $environment,
            );
            $this->assertContains(
                'CREATE '.$relativePath,
                $dryRun,
                "moduark:make {$type} --dry-run omitted its Module-relative target.",
            );
            $target = $application.'/app/Modules/User/'.$relativePath;
            $this->assertFileMissing(
                $target,
                "moduark:make {$type} --dry-run wrote its planned target.",
            );

            $this->artisan($application, $arguments, $environment);
            $this->assertFileExists(
                $target,
                "moduark:make {$type} did not create its Module-owned target.",
            );
        }

        $this->assertFileExists(
            $application.'/app/Modules/User/resources/views/components/billing/invoice-card.blade.php',
            'moduark:make component did not create its Module-owned Blade view.',
        );
        $this->assertFileMissing(
            $application.'/resources/views/components/billing/invoice-card.blade.php',
            'moduark:make component wrote an application-global Blade view.',
        );
        $this->assertFileMissing(
            $application.'/tests/Feature/Billing/ModuleFeatureTest.php',
            'moduark:make test wrote an application-global feature test.',
        );
        $this->assertFileMissing(
            $application.'/tests/Unit/Billing/ModuleUnitTest.php',
            'moduark:make test wrote an application-global unit test.',
        );

        $matchingTestArguments = [
            'moduark:make',
            'User',
            'job',
            'Billing/RebuildInvoiceIndex',
            '--test',
        ];
        $matchingTestDryRun = $this->artisan(
            $application,
            [...$matchingTestArguments, '--dry-run'],
            $environment,
        );
        $this->assertContains(
            'CREATE Jobs/Billing/RebuildInvoiceIndex.php',
            $matchingTestDryRun,
            'moduark:make job --test dry-run omitted the Module-owned job.',
        );
        $this->assertContains(
            'CREATE Tests/Feature/Jobs/Billing/RebuildInvoiceIndexTest.php',
            $matchingTestDryRun,
            'moduark:make job --test dry-run omitted the Module-owned matching test.',
        );
        $this->artisan($application, $matchingTestArguments, $environment);
        $matchingTestPath = $application
            .'/app/Modules/User/Tests/Feature/Jobs/Billing/RebuildInvoiceIndexTest.php';
        $this->assertFileExists(
            $matchingTestPath,
            'moduark:make job --test did not create its Module-owned matching test.',
        );
        $this->assertFileMissing(
            $application.'/tests/Feature/Modules/User/Jobs/Billing/RebuildInvoiceIndexTest.php',
            'moduark:make job --test wrote Laravel native application-global test output.',
        );

        foreach ([
            $application.'/app/Modules/User/Tests/Feature/Billing/ModuleFeatureTest.php',
            $application.'/app/Modules/User/Tests/Unit/Billing/ModuleUnitTest.php',
            $matchingTestPath,
        ] as $moduleTestPath) {
            $this->command([
                $application.'/vendor/bin/phpunit',
                '--colors=never',
                $moduleTestPath,
            ], $application, $environment);
        }

        $migrationArguments = [
            'moduark:make',
            'User',
            'migration',
            'CreateAuditLogsTable',
            '--create=audit_logs',
        ];
        $migrationDryRun = $this->artisan(
            $application,
            [...$migrationArguments, '--dry-run'],
            $environment,
        );
        $this->assertContains(
            'CREATE Database/Migrations/',
            $migrationDryRun,
            'moduark:make migration --dry-run omitted its Module-owned directory.',
        );
        $this->assertContains(
            '_create_audit_logs_table.php',
            $migrationDryRun,
            'moduark:make migration --dry-run omitted its normalized migration filename.',
        );
        $auditMigrations = glob(
            $application.'/app/Modules/User/Database/Migrations/*_create_audit_logs_table.php',
        );
        if (! is_array($auditMigrations) || $auditMigrations !== []) {
            throw new RuntimeException('moduark:make migration --dry-run wrote its planned target.');
        }

        $this->artisan($application, $migrationArguments, $environment);
        $auditMigrations = glob(
            $application.'/app/Modules/User/Database/Migrations/*_create_audit_logs_table.php',
        );
        if (! is_array($auditMigrations) || count($auditMigrations) !== 1) {
            throw new RuntimeException(
                'moduark:make must create exactly one standalone Module-owned migration.',
            );
        }

        $list = $this->artisan($application, ['moduark:list'], $environment);
        $this->assertContains('User', $list, 'moduark:list did not report the generated User Module.');
        $this->assertContains('| 1', $list, 'moduark:list did not use the default Level 1 configuration.');

        $inspection = $this->artisan($application, ['moduark:inspect', 'User'], $environment);
        $this->assertContains('Public API (convention)', $inspection, 'moduark:inspect omitted the Public API.');
        $this->assertContains('UserModule', $inspection, 'moduark:inspect omitted the generated Module.');

        $check = $this->artisan($application, ['moduark:check'], $environment);
        $this->assertContains(
            'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $check,
            'moduark:check did not complete the default Level 1 rule set.',
        );

        $baseline = $this->artisan($application, ['moduark:baseline'], $environment);
        $this->assertContains(
            'Created architecture baseline with 0 violations',
            $baseline,
            'moduark:baseline did not create the initial architecture baseline.',
        );
        $this->assertFileExists(
            $application.'/moduark-baseline.json',
            'moduark:baseline did not write moduark-baseline.json.',
        );

        $suppressionManifest = json_encode([
            'schema_version' => 1,
            'suppressions' => [[
                'rule' => 'cycles',
                'code' => 'MOD-CYCLE-001',
                'file' => 'app/Modules/User/Legacy.php',
                'reason' => 'Clean-install audit fixture.',
            ]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($application.'/moduark-suppressions.json', $suppressionManifest) === false) {
            throw new RuntimeException('Unable to write the clean-install suppression manifest.');
        }

        $suppressionCheck = $this->artisan(
            $application,
            ['moduark:check', '--show-suppressions'],
            $environment,
        );
        $this->assertContains(
            '1 stale suppression entry no longer matches an evaluated violation.',
            $suppressionCheck,
            'moduark:check did not audit a stale architecture suppression.',
        );
        $this->assertContains(
            'Reason: Clean-install audit fixture.',
            $suppressionCheck,
            'moduark:check did not render a suppression reason.',
        );

        $jsonCheck = $this->artisan(
            $application,
            ['moduark:check', '--format=json'],
            $environment,
        );
        $jsonPayload = json_decode($jsonCheck, true, 512, JSON_THROW_ON_ERROR);

        if (
            ! is_array($jsonPayload)
            || ($jsonPayload['schema_version'] ?? null) !== 1
            || ($jsonPayload['status'] ?? null) !== 'passed'
            || ($jsonPayload['exit_code'] ?? null) !== 0
            || ! is_array($jsonPayload['suppressions'] ?? null)
            || ($jsonPayload['suppressions']['stale'] ?? null) !== 1
        ) {
            throw new RuntimeException('moduark:check JSON output did not report a passing result.');
        }

        $githubCheck = $this->artisan(
            $application,
            ['moduark:check', '--format=github'],
            $environment,
        );
        $this->assertContains(
            '::notice title=Moduark architecture check::'
                .'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $githubCheck,
            'moduark:check GitHub output did not report a passing result.',
        );

        $graph = $this->artisan($application, ['moduark:graph'], $environment);
        $this->assertContains('User -> —', $graph, 'moduark:graph did not include the generated User Module.');

        $capabilityGraph = $this->artisan(
            $application,
            ['moduark:graph', '--view=capability'],
            $environment,
        );
        $this->assertContains(
            'User -> —',
            $capabilityGraph,
            'moduark:graph Capability view did not include the generated User Module.',
        );

        $combinedGraph = $this->artisan(
            $application,
            ['moduark:graph', '--view=combined'],
            $environment,
        );
        $this->assertContains(
            'User -> —',
            $combinedGraph,
            'moduark:graph combined view did not include the generated User Module.',
        );

        $moduleCachePath = $application.'/bootstrap/cache/moduark.php';
        $moduleCache = $this->artisan($application, ['moduark:cache'], $environment);
        $this->assertContains(
            'Module cache created successfully: 5 Modules cached.',
            $moduleCache,
            'moduark:cache did not report every generated Module.',
        );
        $this->assertFileExists($moduleCachePath, 'moduark:cache did not create its manifest.');

        $cachedModuleCheck = $this->artisan($application, ['moduark:check'], $environment);
        $this->assertContains(
            'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $cachedModuleCheck,
            'moduark:check did not use the Module cache successfully.',
        );

        $this->artisan($application, ['moduark:clear'], $environment);
        $this->assertFileMissing($moduleCachePath, 'moduark:clear did not remove its manifest.');

        $this->artisan($application, ['optimize'], $environment);
        $this->assertFileExists(
            $moduleCachePath,
            'Laravel optimize did not create the Module cache manifest.',
        );
        $this->artisan($application, ['optimize:clear'], $environment);
        $this->assertFileMissing(
            $moduleCachePath,
            'Laravel optimize:clear did not remove the Module cache manifest.',
        );

        $this->artisan($application, ['config:cache'], $environment);

        try {
            $cachedCheck = $this->artisan($application, ['moduark:check'], $environment);
            $this->assertContains(
                'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
                $cachedCheck,
                'moduark:check did not survive Laravel configuration caching.',
            );
            $cachedInspection = $this->artisan(
                $application,
                ['moduark:inspect', 'User'],
                $environment,
            );
            $this->assertContains(
                'UserModule',
                $cachedInspection,
                'moduark:inspect did not survive Laravel configuration caching.',
            );
        } finally {
            $this->artisan($application, ['config:clear'], $environment);
        }

        echo "PASS Laravel {$major} ({$version})\n";

        return [
            'major' => $major,
            'version' => $version,
        ];
    }

    /** @param array<string, string> $environment */
    private function installAndVerifyBoostSkill(string $application, array $environment): void
    {
        $source = $application.'/vendor/cluion/moduark/resources/boost/skills/moduark-development';
        $installed = $application.'/.agents/skills/moduark-development';

        $this->assertFileMissing(
            $installed,
            'Installing Moduark alone must not write a Codex repository Skill.',
        );
        $this->assertFileExists(
            $source.'/SKILL.md',
            'The installed Moduark package is missing its Laravel Boost Skill source.',
        );

        $this->command([
            'composer',
            'require',
            '--dev',
            'laravel/boost:^2.0',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $application, $environment);

        $boostConfig = json_encode([
            'agents' => ['codex'],
            'packages' => ['cluion/moduark'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($application.'/boost.json', $boostConfig) === false) {
            throw new RuntimeException('Unable to write the clean-install Boost configuration.');
        }

        $arguments = ['boost:install', '--skills', '--no-interaction'];
        $this->artisan($application, $arguments, $environment);
        $this->assertBoostSkillMatchesSource($source, $installed);

        $firstConfig = file_get_contents($application.'/boost.json');
        if ($firstConfig === false) {
            throw new RuntimeException('Unable to read boost.json after Skill installation.');
        }

        $this->assertBoostConfig($firstConfig);
        $firstHashes = $this->directoryFileHashes($installed);

        $this->artisan($application, $arguments, $environment);
        $this->assertBoostSkillMatchesSource($source, $installed);

        $secondConfig = file_get_contents($application.'/boost.json');
        if ($secondConfig === false) {
            throw new RuntimeException('Unable to read boost.json after repeated Skill installation.');
        }

        if ($secondConfig !== $firstConfig) {
            throw new RuntimeException('Repeated Boost Skill installation changed boost.json.');
        }

        if ($this->directoryFileHashes($installed) !== $firstHashes) {
            throw new RuntimeException('Repeated Boost Skill installation changed the installed files.');
        }
    }

    private function assertBoostSkillMatchesSource(string $source, string $installed): void
    {
        $sourceHashes = $this->directoryFileHashes($source);
        $installedHashes = $this->directoryFileHashes($installed);

        if ($installedHashes !== $sourceHashes) {
            throw new RuntimeException(sprintf(
                'Installed Boost Skill does not match its package source: expected %s, got %s.',
                json_encode($sourceHashes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                json_encode($installedHashes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ));
        }
    }

    private function assertBoostConfig(string $contents): void
    {
        $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($config)) {
            throw new RuntimeException('Boost configuration is not a JSON object.');
        }

        foreach (
            [
                'agents' => 'codex',
                'packages' => 'cluion/moduark',
                'skills' => 'moduark-development',
            ] as $key => $expected
        ) {
            $values = $config[$key] ?? null;

            if (! is_array($values) || ! in_array($expected, $values, true)) {
                throw new RuntimeException("Boost configuration [{$key}] is missing [{$expected}].");
            }
        }
    }

    /** @return array<string, string> */
    private function directoryFileHashes(string $directory): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException("Expected Skill directory [{$directory}] does not exist.");
        }

        $prefix = str_replace('\\', '/', rtrim($directory, '/')).'/';
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $hash = hash_file('sha256', $file->getPathname());

            if (! str_starts_with($path, $prefix) || $hash === false) {
                throw new RuntimeException("Unable to hash Skill file [{$path}].");
            }

            $hashes[substr($path, strlen($prefix))] = $hash;
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     */
    private function artisan(string $application, array $arguments, array $environment): string
    {
        return $this->command(
            [PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'],
            $application,
            $environment,
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function command(array $command, string $workingDirectory, array $environment): string
    {
        echo '$ '.$this->commandLabel($command)."\n";
        $errorStream = fopen('php://temp', 'w+');

        if ($errorStream === false) {
            throw new RuntimeException('Unable to open an installation error stream.');
        }

        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => $errorStream,
            ],
            $pipes,
            $workingDirectory,
            $environment,
        );

        if (! is_resource($process)) {
            fclose($errorStream);

            throw new RuntimeException('Unable to start installation command.');
        }

        $output = '';

        while (($line = fgets($pipes[1])) !== false) {
            echo $line;
            $output .= $line;
        }

        fclose($pipes[1]);
        $exitCode = proc_close($process);
        rewind($errorStream);
        $errorOutput = stream_get_contents($errorStream);
        fclose($errorStream);

        if ($errorOutput !== false && $errorOutput !== '') {
            echo $errorOutput;
            $output .= $errorOutput;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Installation command [%s] failed with exit code %d.',
                $this->commandLabel($command),
                $exitCode,
            ));
        }

        return $output;
    }

    /**
     * @param list<string> $command
     */
    private function commandLabel(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('/\A[A-Za-z0-9_@%+=:,\.\/^-]+\z/', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            $command,
        ));
    }

    private function assertOnlyGeneratedModuleFile(string $directory, string $expected): void
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        if ($files !== [$expected]) {
            throw new RuntimeException('moduark:make-module must generate exactly one Module entry file.');
        }
    }

    private function assertFileExists(string $path, string $message): void
    {
        if (! is_file($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertFileMissing(string $path, string $message): void
    {
        if (file_exists($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertPublishedDistribution(string $packageRoot): void
    {
        foreach (PackageArchiveContract::REQUIRED_FILES as $required) {
            $this->assertFileExists(
                $packageRoot.'/'.$required,
                "Published package is missing required file [{$required}].",
            );
        }

        foreach (PackageArchiveContract::EXCLUDED_TREES as $excluded) {
            $this->assertFileMissing(
                $packageRoot.'/'.rtrim($excluded, '/'),
                "Published package contains development tree [{$excluded}].",
            );
        }

        foreach (PackageArchiveContract::EXCLUDED_FILES as $excluded) {
            $this->assertFileMissing(
                $packageRoot.'/'.$excluded,
                "Published package contains development file [{$excluded}].",
            );
        }
    }

    private function assertContains(string $expected, string $actual, string $message): void
    {
        if (! str_contains($actual, $expected)) {
            throw new RuntimeException($message);
        }
    }

    private function assertMatches(string $pattern, string $actual, string $message): void
    {
        if (preg_match($pattern, $actual) !== 1) {
            throw new RuntimeException($message);
        }
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
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($path);
    }
}
