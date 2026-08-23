<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationTarget;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class GenerationExecutorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/moduark-executor-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_removes_every_new_target_when_a_later_generator_fails(): void
    {
        $model = $this->target('model', 'Models/Profile.php');
        $factory = $this->target('factory', 'Database/Factories/ProfileFactory.php');

        $result = (new GenerationExecutor(new Filesystem))->execute(
            new GenerationPlan([$model, $factory]),
            function (GenerationTarget $target): int {
                self::assertTrue(
                    is_dir(dirname($target->filePath()))
                        || mkdir(dirname($target->filePath()), 0755, true),
                );
                self::assertIsInt(file_put_contents($target->filePath(), $target->generatorId()));

                return $target->generatorId() === 'factory' ? 1 : 0;
            },
        );

        self::assertFalse($result->successful());
        self::assertTrue($result->rollbackAttempted());
        self::assertSame([], $result->rollbackFailures());
        self::assertFileDoesNotExist($model->filePath());
        self::assertFileDoesNotExist($factory->filePath());
        self::assertSame([], glob($this->temporaryDirectory.'/*') ?: []);
    }

    public function test_it_restores_overwritten_contents_when_a_later_generator_fails(): void
    {
        $model = $this->target('model', 'Models/Profile.php', true);
        $factory = $this->target('factory', 'Database/Factories/ProfileFactory.php');
        self::assertTrue(mkdir(dirname($model->filePath()), 0755, true));
        self::assertIsInt(file_put_contents($model->filePath(), 'original'));

        $result = (new GenerationExecutor(new Filesystem))->execute(
            new GenerationPlan([$model, $factory]),
            function (GenerationTarget $target): int {
                if ($target->generatorId() === 'factory') {
                    return 1;
                }

                self::assertIsInt(file_put_contents($target->filePath(), 'replacement'));

                return 0;
            },
        );

        self::assertFalse($result->successful());
        self::assertSame('original', file_get_contents($model->filePath()));
        self::assertFileDoesNotExist($factory->filePath());
    }

    public function test_it_reports_a_rollback_failure_instead_of_claiming_atomicity(): void
    {
        $model = $this->target('model', 'Models/Profile.php');
        $factory = $this->target('factory', 'Database/Factories/ProfileFactory.php');
        $filesystem = new class($model->filePath()) extends Filesystem
        {
            public function __construct(private readonly string $undeletable)
            {
            }

            /** @param string|list<string> $paths */
            public function delete($paths): bool
            {
                if ($paths === $this->undeletable) {
                    return false;
                }

                return parent::delete($paths);
            }
        };

        $result = (new GenerationExecutor($filesystem))->execute(
            new GenerationPlan([$model, $factory]),
            function (GenerationTarget $target): int {
                if ($target->generatorId() === 'factory') {
                    return 1;
                }

                self::assertTrue(mkdir(dirname($target->filePath()), 0755, true));
                self::assertIsInt(file_put_contents($target->filePath(), 'created'));

                return 0;
            },
        );

        self::assertFalse($result->successful());
        self::assertSame(['Models/Profile.php'], $result->rollbackFailures());
        self::assertFileExists($model->filePath());
    }

    private function target(
        string $generatorId,
        string $relativePath,
        bool $overwrite = false,
    ): GenerationTarget {
        return new GenerationTarget(
            $generatorId,
            'make:'.$generatorId,
            'Fixture\\'.str_replace('/', '\\', $relativePath),
            $this->temporaryDirectory.'/'.$relativePath,
            $relativePath,
            $overwrite,
            ['name' => 'Fixture'],
        );
    }
}
