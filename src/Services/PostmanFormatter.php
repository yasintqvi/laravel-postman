<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Support\Facades\Storage;
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
        $driver = data_get($this->config, 'output.driver');
        $path = data_get($this->config, 'output.path');
        $filename = (string) data_get($this->config, 'output.filename', 'api_collection');

        // Postman's import dialog filters on .json; add it unless the user chose an extension.
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.json';
        }

        $disk = Storage::build([
            'driver' => $driver,
            'root' => $path,
        ]);

        $contents = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk->put($filename, $contents);

        return $disk->path($filename);
    }
}
