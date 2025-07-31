<?php

namespace YasinTgh\LaravelPostman\Services;

use YasinTgh\LaravelPostman\Collections\Builder;

class PostmanFormatter
{
    public function __construct(
        protected Builder $builder,
        protected array $config,
    ) {}

    public function format(array $routes): array
    {
        return $this->builder->build(
            $routes,
        );
    }

    public function save(array $collection): string
    {
        $output_config = $this->config['output'] ?? [];

        $path = $output_config['path'] ?? storage_path('postman');

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path . '/' . $output_config['filename'];

        file_put_contents(
            $filePath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $filePath;
    }
}
