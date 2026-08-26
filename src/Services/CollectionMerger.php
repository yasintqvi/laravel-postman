<?php

namespace YasinTgh\LaravelPostman\Services;

class CollectionMerger
{
    private array $mergeConfig;

    public function __construct(array $config)
    {
        $this->mergeConfig = $config['cloud']['merge'] ?? [
            'preserve_responses'     => true,
            'preserve_scripts'       => true,
            'preserve_manual_items'  => true,
            'overwrite_descriptions' => false,
        ];
    }

    public function merge(array $remote, array $generated): array
    {
        $remoteIndex = [];
        $this->indexRequests($remote['item'] ?? [], $remoteIndex);

        $matchedKeys = [];
        $mergedItems = $this->mergeTree(
            $generated['item'] ?? [],
            $remote['item'] ?? [],
            $remoteIndex,
            $matchedKeys,
        );

        if ($this->mergeConfig['preserve_manual_items'] ?? true) {
            $mergedItems = $this->appendUnmatched(
                $mergedItems,
                $remote['item'] ?? [],
                $matchedKeys,
            );
        }

        $remote['item'] = $mergedItems;
        $remote['variable'] = $this->mergeVariables(
            $remote['variable'] ?? [],
            $generated['variable'] ?? []
        );

        return $remote;
    }

    private function indexRequests(array $items, array &$index): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($this->isFolder($item)) {
                $this->indexRequests($item['item'] ?? [], $index);
                continue;
            }

            $key = $this->routeKey($item);

            if ($key !== null) {
                $index[$key] = $item;
            }
        }
    }

    private function mergeTree(array $generatedItems, array $remoteItems, array $remoteIndex, array &$matchedKeys): array
    {
        $result = [];

        foreach ($generatedItems as $genItem) {
            if (!is_array($genItem)) {
                continue;
            }

            if ($this->isFolder($genItem)) {
                $result[] = $this->mergeFolder($genItem, $remoteItems, $remoteIndex, $matchedKeys);
                continue;
            }

            $key = $this->routeKey($genItem);

            if ($key !== null && isset($remoteIndex[$key])) {
                $matchedKeys[$key] = true;
                $result[] = $this->mergeRequest($genItem, $remoteIndex[$key]);
            } else {
                $result[] = $genItem;
            }
        }

        return $result;
    }

    private function mergeFolder(array $genFolder, array $remoteItems, array $remoteIndex, array &$matchedKeys): array
    {
        $name         = $genFolder['name'] ?? '';
        $remoteFolder = $this->findFolder($remoteItems, $name);

        $merged = $genFolder;
        $merged['name'] = $name;
        $merged['item'] = $this->mergeTree(
            $genFolder['item'] ?? [],
            $remoteFolder['item'] ?? [],
            $remoteIndex,
            $matchedKeys,
        );

        if ($remoteFolder === null) {
            return $merged;
        }

        if (($this->mergeConfig['preserve_scripts'] ?? true) && isset($remoteFolder['event'])) {
            $merged['event'] = $remoteFolder['event'];
        }

        if (isset($remoteFolder['auth'])) {
            $merged['auth'] = $remoteFolder['auth'];
        }

        if (isset($remoteFolder['protocolProfileBehavior'])) {
            $merged['protocolProfileBehavior'] = $remoteFolder['protocolProfileBehavior'];
        }

        if (!($this->mergeConfig['overwrite_descriptions'] ?? false) && isset($remoteFolder['description'])) {
            $merged['description'] = $remoteFolder['description'];
        }

        return $merged;
    }

    private function mergeRequest(array $generated, array $remote): array
    {
        $merged = $generated;

        if (isset($remote['id'])) {
            $merged['id'] = $remote['id'];
        }

        if (isset($remote['protocolProfileBehavior'])) {
            $merged['protocolProfileBehavior'] = $remote['protocolProfileBehavior'];
        }

        if (($this->mergeConfig['preserve_responses'] ?? true) && !empty($remote['response'])) {
            $merged['response'] = $remote['response'];
        }

        if (($this->mergeConfig['preserve_scripts'] ?? true) && !empty($remote['event'])) {
            $merged['event'] = $remote['event'];
        }

        if (
            !($this->mergeConfig['overwrite_descriptions'] ?? false)
            && !empty($remote['request']['description'])
            && empty($generated['request']['description'])
        ) {
            $merged['request']['description'] = $remote['request']['description'];
        }

        if (!isset($generated['request']['auth']) && isset($remote['request']['auth'])) {
            $merged['request']['auth'] = $remote['request']['auth'];
        }

        $remoteUrl = $remote['request']['url'] ?? null;
        $mergedUrl = $merged['request']['url'] ?? null;

        if (is_array($remoteUrl) && is_array($mergedUrl) && !empty($remoteUrl['query'])) {
            $merged['request']['url']['query'] = $remoteUrl['query'];
        }

        return $merged;
    }

    private function appendUnmatched(array $mergedItems, array $remoteItems, array $matchedKeys): array
    {
        foreach ($remoteItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($this->isFolder($item)) {
                $name  = $item['name'] ?? '';
                $index = $this->findFolderIndex($mergedItems, $name);

                if ($index !== null) {
                    $mergedItems[$index]['item'] = $this->appendUnmatched(
                        $mergedItems[$index]['item'] ?? [],
                        $item['item'] ?? [],
                        $matchedKeys,
                    );
                    continue;
                }

                $children = $this->appendUnmatched([], $item['item'] ?? [], $matchedKeys);

                if ($children !== []) {
                    $folder = $item;
                    $folder['item'] = $children;
                    $mergedItems[] = $folder;
                }

                continue;
            }

            $key = $this->routeKey($item);

            if ($key === null || !isset($matchedKeys[$key])) {
                $mergedItems[] = $item;
            }
        }

        return $mergedItems;
    }

    private function mergeVariables(array $remote, array $generated): array
    {
        $indexed = [];

        foreach ($remote as $var) {
            if (is_array($var) && isset($var['key'])) {
                $indexed[$var['key']] = $var;
            }
        }

        foreach ($generated as $var) {
            if (is_array($var) && isset($var['key']) && !array_key_exists($var['key'], $indexed)) {
                $indexed[$var['key']] = $var;
            }
        }

        return array_values($indexed);
    }

    public function routeKey(array $item): ?string
    {
        if (!isset($item['request'])) {
            return null;
        }

        $request = $item['request'];

        if (!is_array($request)) {
            return null;
        }

        $method = strtoupper((string) ($request['method'] ?? 'GET'));
        $path   = $this->normalizePath($request['url'] ?? '');

        if ($path === '') {
            return null;
        }

        return "{$method}:{$path}";
    }

    private function normalizePath(array|string $url): string
    {
        if (is_array($url)) {
            $raw = (string) ($url['raw'] ?? '');

            if ($raw === '' && isset($url['path'])) {
                $raw = is_array($url['path'])
                    ? implode('/', $url['path'])
                    : (string) $url['path'];
            }
        } else {
            $raw = $url;
        }

        $path = preg_replace('/^(https?:\/\/[^\/]+|\{\{[^}]+\}\})\/?/i', '', $raw) ?? $raw;
        $path = explode('#', $path, 2)[0];
        $path = explode('?', $path, 2)[0];
        $path = preg_replace('/\{\{([^}]+)\}\}/', ':$1', $path) ?? $path;
        $path = preg_replace('/\{([^}]+)\}/', ':$1', $path) ?? $path;
        $path = preg_replace('/\/+/', '/', $path) ?? $path;

        return trim($path, '/');
    }

    private function isFolder(array $item): bool
    {
        return isset($item['item']) && is_array($item['item']) && !isset($item['request']);
    }

    private function findFolder(array $items, string $name): ?array
    {
        $index = $this->findFolderIndex($items, $name);

        return $index === null ? null : $items[$index];
    }

    private function findFolderIndex(array $items, string $name): ?int
    {
        foreach ($items as $index => $item) {
            if (is_array($item) && $this->isFolder($item) && ($item['name'] ?? '') === $name) {
                return (int) $index;
            }
        }

        return null;
    }
}
