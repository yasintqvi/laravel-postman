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

        $routes     = $analyzer->analyze();
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
}
