<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Export;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class GithubCheckReportExporter
{
    public function export(CheckReport $report, string $basePath): string
    {
        $commands = array_map(
            fn (Violation $violation): string => $this->exportViolation($violation, $basePath),
            $report->violations(),
        );

        if (! $report->complete()) {
            $rules = array_map(
                static fn (RuleId $rule): string => $rule->value,
                $report->unavailableRules(),
            );
            $commands[] = $this->command(
                'error',
                ['title' => 'Moduark architecture check'],
                sprintf(
                    "Architecture analysis is incomplete at Level %d (%s).\n"
                        .'Unavailable rule implementations: %s',
                    $report->architecture()->level()->value,
                    $report->architecture()->level()->label(),
                    implode(', ', $rules),
                ),
            );
        } elseif ($commands === []) {
            $count = count($report->results());
            $commands[] = $this->command(
                'notice',
                ['title' => 'Moduark architecture check'],
                sprintf(
                    'Architecture check passed: %d rule%s evaluated at Level %d (%s).',
                    $count,
                    $count === 1 ? '' : 's',
                    $report->architecture()->level()->value,
                    $report->architecture()->level()->label(),
                ),
            );
        }

        $baseline = $report->baseline();

        if ($baseline !== null) {
            $commands[] = $this->command(
                'notice',
                ['title' => 'Moduark architecture baseline'],
                sprintf(
                    'Baseline matched %d existing violation%s from %s; %d stale; %d exceeded.',
                    $baseline->matched(),
                    $baseline->matched() === 1 ? '' : 's',
                    $baseline->path(),
                    $baseline->stale(),
                    $baseline->exceeded(),
                ),
            );
        }

        return implode(PHP_EOL, $commands);
    }

    public function exportToolError(
        string $code,
        string $message,
        ?string $location,
        ?string $suggestion,
        string $basePath,
    ): string {
        $properties = $this->locationProperties($location, $basePath);
        $properties['title'] = $code;
        $details = [$message];

        if ($location !== null) {
            $details[] = 'Location: '.$location;
        }

        if ($suggestion !== null) {
            $details[] = 'Suggestion: '.$suggestion;
        }

        return $this->command('error', $properties, implode("\n", $details));
    }

    private function exportViolation(Violation $violation, string $basePath): string
    {
        $properties = [];

        if ($violation->file() !== null) {
            $properties['file'] = $this->relativePath($violation->file(), $basePath);
        }

        if ($violation->line() !== null) {
            $properties['line'] = $violation->line();
        }

        $properties['title'] = $violation->code().' '.$violation->rule()->value;
        $details = [$violation->message()];

        if ($violation->consumer() !== null || $violation->target() !== null) {
            $details[] = sprintf(
                'Modules: %s -> %s',
                $violation->consumer() ?? '—',
                $violation->target() ?? '—',
            );
        }

        if ($violation->symbol() !== null) {
            $details[] = 'Evidence: '.$violation->symbol();
        }

        if ($violation->suggestion() !== null) {
            $details[] = 'Suggestion: '.$violation->suggestion();
        }

        return $this->command(
            $violation->severity() === Severity::Error ? 'error' : 'warning',
            $properties,
            implode("\n", $details),
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function locationProperties(?string $location, string $basePath): array
    {
        if ($location === null) {
            return [];
        }

        if (preg_match('/\A(.+):([1-9][0-9]*)\z/', $location, $matches) === 1) {
            return [
                'file' => $this->relativePath($matches[1], $basePath),
                'line' => (int) $matches[2],
            ];
        }

        return ['file' => $this->relativePath($location, $basePath)];
    }

    private function relativePath(string $path, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $prefix = $basePath.'/';
        $windowsPath = preg_match('/\A[A-Za-z]:\//', $path) === 1;
        $insideBasePath = $windowsPath
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : strncmp($path, $prefix, strlen($prefix)) === 0;

        return $insideBasePath ? substr($path, strlen($prefix)) : $path;
    }

    /**
     * @param array<string, int|string> $properties
     */
    private function command(string $type, array $properties, string $message): string
    {
        $attributes = [];

        foreach ($properties as $name => $value) {
            $attributes[] = $name.'='.$this->escapeProperty((string) $value);
        }

        return '::'.$type
            .($attributes === [] ? '' : ' '.implode(',', $attributes))
            .'::'.$this->escapeData($message);
    }

    private function escapeData(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n"],
            ['%25', '%0D', '%0A'],
            $value,
        );
    }

    private function escapeProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }
}
