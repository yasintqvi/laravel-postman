<?php

namespace YasinTgh\LaravelPostman\Collections;

use YasinTgh\LaravelPostman\Collections\FolderStrategy;

class Builder
{
    public function __construct(
        protected FolderStrategy $folderStrategy,
        protected array $config
    ) {}

    public function build(array $routes): array
    {
        return [
            'info' => $this->buildInfo(),
            'item' => $this->folderStrategy->organize($routes),
            'variable' => [
                ['key' => 'base_url', 'value' => $this->config['base_url']]
            ]
        ];
    }

    protected function buildInfo(): array
    {
        return [
            'name' => $this->config['name'],
            'description' => $this->config['description'],
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
        ];
    }
}
