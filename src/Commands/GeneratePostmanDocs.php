<?php

namespace YasinTgh\LaravelPostman\Commands;

use Illuminate\Console\Command;
use Throwable;
use YasinTgh\LaravelPostman\Contracts\CloudSyncServiceInterface;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\Services\PostmanFormatter;

class GeneratePostmanDocs extends Command
{
    protected $signature = 'postman:generate
                            {--push     : Fetch remote collection, merge changes, and push back to Postman Cloud}
                            {--dry-run  : Run the merge locally and preview what would change without pushing}';

    protected $description = 'Generate a Postman collection from Laravel routes';

    public function handle(
        RouteAnalyzerInterface $analyzer,
        PostmanFormatter $formatter,
        CloudSyncServiceInterface $syncService,
    ): int {
        $this->info('Analysing routes…');

        $routes = $analyzer->analyze();

        $skipped = method_exists($analyzer, 'skipped') ? $analyzer->skipped() : [];
        foreach ($skipped as $route) {
            $this->warn(sprintf(
                '  Skipped [%s] %s: %s',
                implode('|', $route['methods']),
                $route['uri'],
                $route['reason']
            ));
        }

        $this->info(sprintf('%d route(s) documented, %d skipped.', count($routes), count($skipped)));

        $collection = $formatter->format($routes);
        $savedPath  = $formatter->save($collection);

        $this->info("Collection saved: {$savedPath}");

        if ($this->option('push') || $this->option('dry-run')) {
            return $this->runCloudSync($syncService, $collection);
        }

        return self::SUCCESS;
    }

    private function runCloudSync(CloudSyncServiceInterface $syncService, array $generated): int
    {
        $isDryRun = $this->option('dry-run');

        try {
            $this->info('Fetching remote collection from Postman Cloud…');
            $remote = $syncService->fetchRemoteCollection();
            $this->line('  Remote collection: ' . ($remote['info']['name'] ?? 'unknown'));

            $this->info('Merging…');
            $merged = $syncService->merge($remote, $generated);

            $this->printMergeReport($syncService->lastMergeReport(), (bool) $isDryRun);

            if ($isDryRun) {
                $this->warn('[dry-run] Merge complete — no changes pushed to Postman Cloud.');
                return self::SUCCESS;
            }

            $this->info('Pushing to Postman Cloud…');
            $syncService->pushCollection($merged);
            $this->info('Done. Collection synced successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Prints counts for every push; a dry run also lists the requests
     * so the user can see what would change before pushing.
     */
    private function printMergeReport(array $report, bool $detailed): void
    {
        $sections = [
            'added'     => 'New requests',
            'updated'   => 'Updated requests (remote responses, scripts and ids kept)',
            'preserved' => 'Remote-only requests kept',
            'removed'   => 'Stale requests removed',
        ];

        foreach ($sections as $key => $label) {
            $items = $report[$key] ?? [];

            if ($key === 'removed' && $items === []) {
                continue;
            }

            $this->line(sprintf('  %s: %d', $label, count($items)));

            if (!$detailed) {
                continue;
            }

            foreach (array_slice($items, 0, 50) as $item) {
                // Keys are "METHOD:path"; only the first colon separates them.
                $parts = explode(':', (string) $item, 2);
                $this->line('    - ' . (count($parts) === 2 ? "{$parts[0]} /{$parts[1]}" : ($item ?: '(unnamed)')));
            }

            if (count($items) > 50) {
                $this->line(sprintf('    … and %d more', count($items) - 50));
            }
        }
    }
}
