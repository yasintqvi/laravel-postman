<?php

namespace YasinTgh\LaravelPostman\Commands;

use Illuminate\Console\Command;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\Services\PostmanFormatter;

class GeneratePostmanDocs extends Command
{
    protected $signature = 'postman:generate';
    protected $description = 'Generate Postman collection';

    public function handle(
        RouteAnalyzerInterface $analyzer,
        PostmanFormatter $formatter,
    ) {
        $routes = $analyzer->analyze();
        $collection = $formatter->format($routes);
        $path = $formatter->save($collection);

        $this->info("Postman collection generated at: {$path}");
    }
}
