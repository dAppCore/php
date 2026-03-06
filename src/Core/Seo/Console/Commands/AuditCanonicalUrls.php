<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Console\Commands;

use Core\Seo\Validation\CanonicalUrlValidator;
use Illuminate\Console\Command;

/**
 * Audit canonical URLs across the site for conflicts and issues.
 */
class AuditCanonicalUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:audit-canonical
                            {--fix : Attempt to fix detected issues}
                            {--json : Output results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit canonical URLs for conflicts and issues';

    /**
     * Execute the console command.
     */
    public function handle(CanonicalUrlValidator $validator): int
    {
        $this->info('Auditing canonical URLs...');
        $this->newLine();

        $audit = $validator->audit();
        $summary = $audit['summary'];

        if ($this->option('json')) {
            $this->outputJson($audit);

            return self::SUCCESS;
        }

        // Summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total SEO records', $summary['total']],
                ['With canonical URL', $summary['with_canonical']],
                ['Without canonical URL', $summary['without_canonical']],
                ['Duplicate canonical URLs', $summary['duplicate_count']],
                ['Total issues', $summary['issue_count']],
            ]
        );

        $this->newLine();

        // Report duplicates
        if ($audit['duplicates']->isNotEmpty()) {
            $this->warn('Duplicate Canonical URLs:');
            foreach ($audit['duplicates'] as $url => $records) {
                $this->line("  [$url]:");
                foreach ($records as $record) {
                    $this->line("    - {$record->seoable_type} #{$record->seoable_id}");
                }
            }
            $this->newLine();
        }

        // Report protocol issues
        if ($audit['protocol_issues']->isNotEmpty()) {
            $this->warn('Protocol Issues (HTTP instead of HTTPS):');
            foreach ($audit['protocol_issues'] as $record) {
                $this->line("  - {$record->seoable_type} #{$record->seoable_id}: {$record->canonical_url}");
            }
            $this->newLine();
        }

        // Report www inconsistencies
        if ($audit['www_inconsistencies']->isNotEmpty()) {
            $this->warn('WWW Inconsistencies:');
            foreach ($audit['www_inconsistencies'] as $record) {
                $this->line("  - {$record->seoable_type} #{$record->seoable_id}: {$record->canonical_url}");
            }
            $this->newLine();
        }

        // Report self-referencing issues
        if ($audit['self_referencing']->isNotEmpty()) {
            $this->warn('Self-Referencing Issues (canonical differs from resource URL):');
            foreach ($audit['self_referencing'] as $record) {
                $this->line("  - {$record->seoable_type} #{$record->seoable_id}: {$record->canonical_url}");
            }
            $this->newLine();
        }

        // Final status
        if ($summary['issue_count'] === 0) {
            $this->info('No canonical URL issues detected.');

            return self::SUCCESS;
        }

        $this->error("{$summary['issue_count']} issue(s) detected.");

        if ($this->option('fix')) {
            $this->attemptFixes($audit, $validator);
        } else {
            $this->line('Run with --fix to attempt automatic fixes.');
        }

        return self::FAILURE;
    }

    /**
     * Attempt to fix detected issues.
     */
    protected function attemptFixes(array $audit, CanonicalUrlValidator $validator): void
    {
        $this->newLine();
        $this->info('Attempting fixes...');

        $fixed = 0;

        // Fix protocol issues (HTTP -> HTTPS)
        foreach ($audit['protocol_issues'] as $record) {
            $oldUrl = $record->canonical_url;
            $newUrl = str_replace('http://', 'https://', $oldUrl);

            $record->canonical_url = $newUrl;
            $record->save();

            $this->line("  Fixed protocol: {$oldUrl} -> {$newUrl}");
            $fixed++;
        }

        // Note: Duplicates and www inconsistencies require manual review
        if ($audit['duplicates']->isNotEmpty()) {
            $this->warn('  Duplicate canonical URLs require manual review.');
        }

        if ($audit['www_inconsistencies']->isNotEmpty()) {
            $this->warn('  WWW inconsistencies require manual review.');
        }

        $this->newLine();
        $this->info("Fixed {$fixed} issue(s).");
    }

    /**
     * Output results as JSON.
     */
    protected function outputJson(array $audit): void
    {
        $output = [
            'summary' => $audit['summary'],
            'duplicates' => $audit['duplicates']->map(fn ($group) => $group->map(fn ($r) => [
                'id' => $r->id,
                'seoable_type' => $r->seoable_type,
                'seoable_id' => $r->seoable_id,
                'canonical_url' => $r->canonical_url,
            ])->values())->toArray(),
            'protocol_issues' => $audit['protocol_issues']->map(fn ($r) => [
                'id' => $r->id,
                'seoable_type' => $r->seoable_type,
                'seoable_id' => $r->seoable_id,
                'canonical_url' => $r->canonical_url,
            ])->values()->toArray(),
            'www_inconsistencies' => $audit['www_inconsistencies']->map(fn ($r) => [
                'id' => $r->id,
                'seoable_type' => $r->seoable_type,
                'seoable_id' => $r->seoable_id,
                'canonical_url' => $r->canonical_url,
            ])->values()->toArray(),
        ];

        $this->output->writeln(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
