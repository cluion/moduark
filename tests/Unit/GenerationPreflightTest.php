<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use PHPUnit\Framework\TestCase;

final class GenerationPreflightTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/moduark-preflight-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_reports_every_existing_target_before_generation(): void
    {
        $model = $this->target('model', 'Model.php');
        $controller = $this->target('controller', 'Controller.php');
        self::assertIsInt(file_put_contents($model->filePath(), 'model'));
        self::assertIsInt(file_put_contents($controller->filePath(), 'controller'));

        $collisions = (new GenerationPreflight)->collisions(
            new GenerationPlan([$model, $controller]),
        );

        self::assertSame(
            [$controller->filePath(), $model->filePath()],
            array_map(
                static fn (GenerationTarget $target): string => $target->filePath(),
                $collisions,
            ),
        );
    }

    public function test_force_allows_existing_files_but_not_duplicate_planned_paths(): void
    {
        $first = $this->target('model', 'Shared.php', true);
        $duplicate = $this->target('controller', 'Shared.php', true);
        self::assertIsInt(file_put_contents($first->filePath(), 'existing'));

        $collisions = (new GenerationPreflight)->collisions(
            new GenerationPlan([$first, $duplicate]),
        );

        self::assertSame([$duplicate], $collisions);
    }

    public function test_force_does_not_allow_a_directory_at_a_planned_file_path(): void
    {
        $target = $this->target('factory', 'ProfileFactory.php', true);
        self::assertTrue(mkdir($target->filePath()));

        self::assertSame(
            [$target],
            (new GenerationPreflight)->collisions(new GenerationPlan([$target])),
        );
    }

    private function target(string $generatorId, string $file, bool $overwrite = false): GenerationTarget
    {
        return new GenerationTarget(
            $generatorId,
            'make:'.$generatorId,
            'Fixture\\'.$file,
            $this->temporaryDirectory.'/'.$file,
            $file,
            $overwrite,
            ['name' => 'Fixture\\'.$file, '--no-interaction' => true],
        );
    }
}
