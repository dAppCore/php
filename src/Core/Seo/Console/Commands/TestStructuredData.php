<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Console\Commands;

use Core\Seo\Validation\StructuredDataTester;
use Illuminate\Console\Command;

/**
 * Tests structured data (JSON-LD) from a URL or file.
 *
 * Validates against schema.org specifications and checks for
 * rich results eligibility.
 *
 * Usage:
 *   php artisan seo:test-structured-data https://example.com
 *   php artisan seo:test-structured-data schema.json --file
 *   php artisan seo:test-structured-data https://example.com --json
 */
class TestStructuredData extends Command
{
    /**
     * Command signature.
     *
     * @var string
     */
    protected $signature = 'seo:test-structured-data
                            {source : URL or file path to test}
                            {--file : Treat source as a file path instead of URL}
                            {--json : Output results as JSON}
                            {--verbose : Show detailed output}';

    /**
     * Command description.
     *
     * @var string
     */
    protected $description = 'Test structured data (JSON-LD) against schema.org specifications';

    /**
     * Execute the command.
     */
    public function handle(StructuredDataTester $tester): int
    {
        $source = $this->argument('source');
        $isFile = $this->option('file');
        $asJson = $this->option('json');

        if ($isFile) {
            return $this->testFile($tester, $source, $asJson);
        }

        return $this->testUrl($tester, $source, $asJson);
    }

    /**
     * Test a URL.
     */
    protected function testUrl(StructuredDataTester $tester, string $url, bool $asJson): int
    {
        $this->components->info("Testing URL: $url");
        $this->newLine();

        $result = $tester->testUrl($url);

        if ($asJson) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['valid'] ? self::SUCCESS : self::FAILURE;
        }

        return $this->displayUrlResult($result);
    }

    /**
     * Test a file.
     */
    protected function testFile(StructuredDataTester $tester, string $path, bool $asJson): int
    {
        if (! file_exists($path)) {
            $this->components->error("File not found: $path");

            return self::FAILURE;
        }

        $content = file_get_contents($path);
        $schema = json_decode($content, true);

        if ($schema === null) {
            $this->components->error('Invalid JSON in file');

            return self::FAILURE;
        }

        $this->components->info("Testing file: $path");
        $this->newLine();

        $report = $tester->generateReport($schema);

        if ($asJson) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['summary']['valid'] ? self::SUCCESS : self::FAILURE;
        }

        return $this->displayReport($report);
    }

    /**
     * Display URL test result.
     */
    protected function displayUrlResult(array $result): int
    {
        if ($result['schemas_found'] === 0) {
            $this->components->warn('No structured data found on this page.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Schemas found', (string) $result['schemas_found']);
        $this->components->twoColumnDetail(
            'Valid',
            $result['valid'] ? '<fg=green>Yes</>' : '<fg=red>No</>'
        );
        $this->components->twoColumnDetail('Total errors', (string) $result['summary']['total_errors']);
        $this->components->twoColumnDetail('Total warnings', (string) $result['summary']['total_warnings']);

        foreach ($result['results'] as $index => $schemaResult) {
            $this->newLine();
            $this->components->info('Schema '.($index + 1));

            $this->displaySchemaResult($schemaResult);
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display a schema result.
     */
    protected function displaySchemaResult(array $result): void
    {
        if (! empty($result['types_found'])) {
            $this->components->twoColumnDetail('Types', implode(', ', $result['types_found']));
        }

        if (! empty($result['rich_results'])) {
            $this->components->twoColumnDetail(
                'Rich results eligible',
                '<fg=green>'.implode(', ', $result['rich_results']).'</>'
            );
        }

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->components->error('Errors:');
            foreach ($result['errors'] as $error) {
                $this->line("  <fg=red>*</> [{$error['path']}] {$error['message']}");
                if ($this->option('verbose') && isset($error['fix'])) {
                    $this->line("    <fg=gray>Fix: {$error['fix']}</>");
                }
            }
        }

        if (! empty($result['warnings'])) {
            $this->newLine();
            $this->components->warn('Warnings:');
            foreach ($result['warnings'] as $warning) {
                $this->line("  <fg=yellow>*</> [{$warning['path']}] {$warning['message']}");
                if ($this->option('verbose') && isset($warning['fix'])) {
                    $this->line("    <fg=gray>Fix: {$warning['fix']}</>");
                }
            }
        }

        if (! empty($result['info'])) {
            $this->newLine();
            foreach ($result['info'] as $info) {
                $this->line("  <fg=blue>i</> $info");
            }
        }
    }

    /**
     * Display a full report.
     */
    protected function displayReport(array $report): int
    {
        // Summary
        $this->components->twoColumnDetail(
            'Valid',
            $report['summary']['valid'] ? '<fg=green>Yes</>' : '<fg=red>No</>'
        );
        $this->components->twoColumnDetail('Score', $this->formatScore($report['score']));
        $this->components->twoColumnDetail('Errors', (string) $report['summary']['error_count']);
        $this->components->twoColumnDetail('Warnings', (string) $report['summary']['warning_count']);

        if (! empty($report['types'])) {
            $this->components->twoColumnDetail('Types', implode(', ', $report['types']));
        }

        if (! empty($report['rich_results'])) {
            $this->newLine();
            $this->components->info('Rich Results Eligible:');
            foreach ($report['rich_results'] as $feature) {
                $this->line("  <fg=green>*</> $feature");
            }
        }

        // Errors
        if (! empty($report['errors'])) {
            $this->newLine();
            $this->components->error('Errors:');
            foreach ($report['errors'] as $error) {
                $this->line("  <fg=red>*</> [{$error['path']}] {$error['message']}");
                $this->line("    <fg=gray>Explanation: {$error['explanation']}</>");
                $this->line("    <fg=cyan>Fix: {$error['fix']}</>");
            }
        }

        // Warnings
        if (! empty($report['warnings'])) {
            $this->newLine();
            $this->components->warn('Warnings:');
            foreach ($report['warnings'] as $warning) {
                $this->line("  <fg=yellow>*</> [{$warning['path']}] {$warning['message']}");
                if ($this->option('verbose')) {
                    $this->line("    <fg=gray>Explanation: {$warning['explanation']}</>");
                    $this->line("    <fg=cyan>Fix: {$warning['fix']}</>");
                }
            }
        }

        // Recommendations
        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->components->info('Recommendations:');
            foreach ($report['recommendations'] as $rec) {
                $this->line("  <fg=blue>*</> $rec");
            }
        }

        return $report['summary']['valid'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Format score for display.
     */
    protected function formatScore(int $score): string
    {
        $color = match (true) {
            $score >= 80 => 'green',
            $score >= 50 => 'yellow',
            default => 'red',
        };

        return "<fg=$color>$score/100</>";
    }
}
