<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use JsonException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LargeLevelTwo\LargeLevelTwoFixture;

final class InteractiveGraphExamplesTest extends TestCase
{
    /** @throws JsonException */
    public function test_interactive_graph_data_matches_the_executable_fixture(): void
    {
        $root = dirname(__DIR__, 2);
        $html = file_get_contents($root.'/docs/examples/interactive-graphs.html');

        self::assertNotFalse($html);
        $matched = preg_match(
            '/<script id="graph-data" type="application\/json">\s*(.*?)\s*<\/script>/s',
            $html,
            $matches,
        );

        if ($matched !== 1) {
            self::fail('Interactive graph data payload was not found.');
        }

        /** @var array{
         *     modules: list<array{id: string, name: string, role: string}>,
         *     dependencies: list<array{source: string, target: string}>,
         *     capabilities: list<array{
         *         id: string,
         *         name: string,
         *         provider: string,
         *         consumers: list<string>
         *     }>
         * } $data
         */
        $data = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $moduleNamesById = [];

        foreach ($data['modules'] as $module) {
            $moduleNamesById[$module['id']] = $module['name'];
        }

        $registry = LargeLevelTwoFixture::registry();
        $compiler = new ModuleMetadataCompiler;
        $moduleGraph = (new ModuleGraphBuilder($registry, $compiler))->build();
        $capabilityGraph = (new CapabilityGraphBuilder(
            $registry,
            $compiler,
            new CapabilityResolver,
        ))->build();

        self::assertSame(
            array_map(static fn ($module): string => $module->name(), $moduleGraph->nodes()),
            array_column($data['modules'], 'name'),
        );

        $expectedDependencies = [];

        foreach ($moduleGraph->edges() as $edge) {
            $expectedDependencies[] = [
                $moduleGraph->node($edge->source())->name(),
                $moduleGraph->node($edge->target())->name(),
            ];
        }

        $actualDependencies = array_map(
            static fn (array $edge): array => [
                $moduleNamesById[$edge['source']],
                $moduleNamesById[$edge['target']],
            ],
            $data['dependencies'],
        );

        self::assertSame($expectedDependencies, $actualDependencies);

        $capabilityNamesById = [];

        foreach ($data['capabilities'] as $capability) {
            $capabilityNamesById[$capability['id']] = $capability['name'];
        }

        self::assertSame(
            array_map(
                static fn ($capability): string => $capability->name(),
                $capabilityGraph->capabilities(),
            ),
            array_column($data['capabilities'], 'name'),
        );

        $expectedCapabilityEdges = [];

        foreach ($capabilityGraph->edges() as $edge) {
            $expectedCapabilityEdges[] = [
                $edge->type()->value,
                $capabilityGraph->module($edge->module())->name(),
                $capabilityGraph->capability($edge->capability())->name(),
            ];
        }

        $actualCapabilityEdges = [];

        foreach ($data['capabilities'] as $capability) {
            $actualCapabilityEdges[] = [
                'provides',
                $moduleNamesById[$capability['provider']],
                $capabilityNamesById[$capability['id']],
            ];

            foreach ($capability['consumers'] as $consumer) {
                $actualCapabilityEdges[] = [
                    'requires',
                    $moduleNamesById[$consumer],
                    $capabilityNamesById[$capability['id']],
                ];
            }
        }

        self::assertSame($expectedCapabilityEdges, $actualCapabilityEdges);
        self::assertCount(8, $data['modules']);
        self::assertCount(12, $data['dependencies']);
        self::assertCount(5, $data['capabilities']);
        self::assertCount(17, $actualCapabilityEdges);
        self::assertStringNotContainsString('<script src=', $html);
        self::assertStringNotContainsString('href="http', $html);
    }
}
