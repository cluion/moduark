<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Generation\GenerationFileTemplate;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPlanValidator;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\ModuleMakerTarget;
use PHPUnit\Framework\TestCase;

final class GenerationPlanValidatorTest extends TestCase
{
    public function test_it_accepts_a_complete_module_owned_template_plan(): void
    {
        $validator = new GenerationPlanValidator;
        $makerTarget = $this->makerTarget();
        $plan = new GenerationPlan([$this->target($makerTarget)]);

        $validator->validate(
            $this->descriptor(),
            $makerTarget,
            $plan,
            $this->options(),
        );

        self::assertCount(1, $plan->targets());
    }

    public function test_it_rejects_an_empty_custom_plan(): void
    {
        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage('the plan must contain at least one target');

        (new GenerationPlanValidator)->validate(
            $this->descriptor(),
            $this->makerTarget(),
            new GenerationPlan([]),
            $this->options(),
        );
    }

    public function test_it_rejects_a_target_outside_the_selected_module(): void
    {
        $makerTarget = $this->makerTarget();
        $target = new GenerationTarget(
            'value-object',
            null,
            $makerTarget->className(),
            '/tmp/outside.php',
            $makerTarget->moduleRelativePath(),
            false,
            [],
            new GenerationFileTemplate('/tmp/stub', []),
        );

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage('must resolve exactly inside the selected Module');

        (new GenerationPlanValidator)->validate(
            $this->descriptor(),
            $makerTarget,
            new GenerationPlan([$target]),
            $this->options(),
        );
    }

    public function test_it_rejects_generator_id_spoofing_and_artisan_delegation(): void
    {
        $makerTarget = $this->makerTarget();
        $spoofed = new GenerationTarget(
            'model',
            'make:model',
            $makerTarget->className(),
            $makerTarget->filePath(),
            $makerTarget->moduleRelativePath(),
            false,
            ['name' => $makerTarget->className()],
        );

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage('must retain generator ID [value-object]');

        (new GenerationPlanValidator)->validate(
            $this->descriptor(),
            $makerTarget,
            new GenerationPlan([$spoofed]),
            $this->options(),
        );
    }

    public function test_it_rejects_custom_artisan_delegation_with_the_correct_id(): void
    {
        $makerTarget = $this->makerTarget();
        $delegated = new GenerationTarget(
            'value-object',
            'make:class',
            $makerTarget->className(),
            $makerTarget->filePath(),
            $makerTarget->moduleRelativePath(),
            false,
            ['name' => $makerTarget->className()],
        );

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage('must use a template without Artisan delegate parameters');

        (new GenerationPlanValidator)->validate(
            $this->descriptor(),
            $makerTarget,
            new GenerationPlan([$delegated]),
            $this->options(),
        );
    }

    public function test_it_rejects_overwrite_intent_without_force(): void
    {
        $makerTarget = $this->makerTarget();

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage('requests overwrite without --force');

        (new GenerationPlanValidator)->validate(
            $this->descriptor(),
            $makerTarget,
            new GenerationPlan([$this->target($makerTarget, true)]),
            $this->options(),
        );
    }

    private function descriptor(): GeneratorDescriptor
    {
        return new readonly class implements GeneratorDescriptor
        {
            public function id(): string
            {
                return 'value-object';
            }

            public function targetNamespace(): string
            {
                return 'ValueObjects';
            }

            public function supportedOptions(): array
            {
                return ['force'];
            }

            public function plan(
                ModuleMakerTarget $target,
                GenerationOptions $options,
            ): GenerationPlan {
                return new GenerationPlan([]);
            }
        };
    }

    private function makerTarget(): ModuleMakerTarget
    {
        return new ModuleMakerTarget(
            'Fixture\\Modules\\User\\ValueObjects\\Money',
            '/application/app/Modules/User/ValueObjects/Money.php',
            'ValueObjects/Money.php',
            '/application/app/Modules/User',
            'User',
            'Fixture\\Modules\\User',
            'Money',
        );
    }

    private function target(ModuleMakerTarget $target, bool $overwrite = false): GenerationTarget
    {
        return new GenerationTarget(
            'value-object',
            null,
            $target->className(),
            $target->filePath(),
            $target->moduleRelativePath(),
            $overwrite,
            [],
            new GenerationFileTemplate('/package/stubs/value-object.stub', []),
        );
    }

    private function options(bool $force = false): GenerationOptions
    {
        return new GenerationOptions(
            force: $force,
            invokable: false,
            resource: false,
            api: false,
            factory: false,
            migration: false,
            create: null,
            table: null,
            intBacked: false,
            stringBacked: false,
            inbound: false,
            render: false,
            report: false,
            collection: false,
            jsonApi: false,
            model: null,
            guard: null,
            implicit: false,
            event: null,
            queued: false,
            sync: false,
            batched: false,
            markdown: null,
            view: null,
            viewOnly: false,
            inline: false,
            path: null,
            extension: null,
            unit: false,
            test: false,
            pest: false,
            phpunit: false,
        );
    }
}
