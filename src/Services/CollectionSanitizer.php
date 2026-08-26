<?php

namespace YasinTgh\LaravelPostman\Services;

class CollectionSanitizer
{
    public function sanitize(array $collection): array
    {
        $collection = $this->only($collection, [
            'info',
            'item',
            'event',
            'variable',
            'auth',
            'protocolProfileBehavior',
        ]);

        if (isset($collection['info']) && is_array($collection['info'])) {
            $collection['info'] = $this->only($collection['info'], [
                'name',
                '_postman_id',
                'description',
                'version',
                'schema',
            ]);
        }

        if (isset($collection['item']) && is_array($collection['item'])) {
            $collection['item'] = $this->sanitizeItems($collection['item']);
        }

        if (isset($collection['variable']) && is_array($collection['variable'])) {
            $collection['variable'] = $this->sanitizeVariables($collection['variable']);
        }

        if (isset($collection['event']) && is_array($collection['event'])) {
            $collection['event'] = $this->sanitizeEvents($collection['event']);
        }

        if (isset($collection['auth']) && is_array($collection['auth'])) {
            $collection['auth'] = $this->sanitizeAuth($collection['auth']);
        }

        return $collection;
    }

    private function sanitizeItems(array $items): array
    {
        $sanitized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($this->isFolder($item)) {
                $folder = $this->sanitizeFolder($item);
                if ($folder !== null) {
                    $sanitized[] = $folder;
                }
                continue;
            }

            if (isset($item['request'])) {
                $sanitized[] = $this->sanitizeRequestItem($item);
            }
        }

        return $sanitized;
    }

    private function sanitizeFolder(array $item): ?array
    {
        $children = $this->sanitizeItems($item['item'] ?? []);

        $folder = $this->only($item, [
            'name',
            'description',
            'variable',
            'item',
            'event',
            'auth',
            'protocolProfileBehavior',
        ]);

        $folder['item'] = $children;

        if (isset($folder['variable']) && is_array($folder['variable'])) {
            $folder['variable'] = $this->sanitizeVariables($folder['variable']);
        }

        if (isset($folder['event']) && is_array($folder['event'])) {
            $folder['event'] = $this->sanitizeEvents($folder['event']);
        }

        if (isset($folder['auth']) && is_array($folder['auth'])) {
            $folder['auth'] = $this->sanitizeAuth($folder['auth']);
        }

        return $folder;
    }

    private function sanitizeRequestItem(array $item): array
    {
        $sanitized = $this->only($item, [
            'id',
            'name',
            'description',
            'variable',
            'event',
            'request',
            'response',
            'protocolProfileBehavior',
        ]);

        $sanitized['request'] = $this->sanitizeRequest($item['request']);

        if (isset($sanitized['variable']) && is_array($sanitized['variable'])) {
            $sanitized['variable'] = $this->sanitizeVariables($sanitized['variable']);
        }

        if (isset($sanitized['event']) && is_array($sanitized['event'])) {
            $sanitized['event'] = $this->sanitizeEvents($sanitized['event']);
        }

        if (isset($sanitized['response']) && is_array($sanitized['response'])) {
            $sanitized['response'] = array_values(array_filter(
                array_map(
                    fn (mixed $response) => is_array($response) ? $this->sanitizeResponse($response) : null,
                    $sanitized['response']
                )
            ));
        }

        return $sanitized;
    }

    private function sanitizeRequest(mixed $request): array|string
    {
        if (!is_array($request)) {
            return is_string($request) ? $request : '';
        }

        $sanitized = $this->only($request, [
            'url',
            'auth',
            'proxy',
            'certificate',
            'method',
            'description',
            'header',
            'body',
        ]);

        if (isset($sanitized['header']) && is_array($sanitized['header'])) {
            $sanitized['header'] = $this->sanitizeHeaders($sanitized['header']);
        }

        if (isset($sanitized['auth']) && is_array($sanitized['auth'])) {
            $sanitized['auth'] = $this->sanitizeAuth($sanitized['auth']);
        }

        if (array_key_exists('body', $sanitized)) {
            $body = $this->sanitizeBody($sanitized['body']);
            if ($body === null) {
                unset($sanitized['body']);
            } else {
                $sanitized['body'] = $body;
            }
        }

        return $sanitized;
    }

    private function sanitizeBody(mixed $body): ?array
    {
        if (!is_array($body) || $body === []) {
            return null;
        }

        $sanitized = $this->only($body, [
            'mode',
            'raw',
            'urlencoded',
            'formdata',
            'file',
            'graphql',
            'options',
            'disabled',
        ]);

        if (($sanitized['mode'] ?? null) === 'raw' && isset($sanitized['raw']) && !is_string($sanitized['raw'])) {
            $sanitized['raw'] = json_encode($sanitized['raw'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (array_key_exists('options', $sanitized)) {
            $options = $sanitized['options'];

            if (!is_array($options) || $options === [] || array_is_list($options)) {
                if (($sanitized['mode'] ?? null) === 'raw') {
                    $sanitized['options'] = ['raw' => ['language' => 'json']];
                } else {
                    unset($sanitized['options']);
                }
            }
        } elseif (($sanitized['mode'] ?? null) === 'raw') {
            $sanitized['options'] = ['raw' => ['language' => 'json']];
        }

        return $sanitized;
    }

    private function sanitizeResponse(array $response): array
    {
        $sanitized = $this->only($response, [
            'id',
            'name',
            'originalRequest',
            'responseTime',
            'timings',
            'header',
            'cookie',
            'body',
            'status',
            'code',
        ]);

        if (isset($sanitized['originalRequest'])) {
            $sanitized['originalRequest'] = $this->sanitizeRequest($sanitized['originalRequest']);
        }

        if (isset($sanitized['header']) && is_array($sanitized['header'])) {
            $sanitized['header'] = $this->sanitizeHeaders($sanitized['header']);
        }

        if (array_key_exists('body', $sanitized) && $sanitized['body'] !== null && !is_string($sanitized['body'])) {
            $sanitized['body'] = is_array($sanitized['body'])
                ? (json_encode($sanitized['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
                : (string) $sanitized['body'];
        }

        return $sanitized;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $header) {
            if (is_string($header)) {
                $sanitized[] = $header;
                continue;
            }

            if (!is_array($header) || !isset($header['key'])) {
                continue;
            }

            $item = $this->only($header, ['key', 'value', 'disabled', 'description']);
            $item['value'] = isset($item['value']) ? (string) $item['value'] : '';
            $sanitized[] = $item;
        }

        return $sanitized;
    }

    private function sanitizeVariables(array $variables): array
    {
        $sanitized = [];

        foreach ($variables as $variable) {
            if (!is_array($variable) || !isset($variable['key'])) {
                continue;
            }

            $sanitized[] = $this->only($variable, [
                'id',
                'key',
                'value',
                'type',
                'name',
                'description',
                'system',
                'disabled',
            ]);
        }

        return $sanitized;
    }

    private function sanitizeEvents(array $events): array
    {
        $sanitized = [];

        foreach ($events as $event) {
            if (!is_array($event) || !isset($event['listen'])) {
                continue;
            }

            $item = $this->only($event, ['id', 'listen', 'script', 'disabled']);

            if (isset($item['script']) && is_array($item['script'])) {
                $item['script'] = $this->only($item['script'], ['id', 'type', 'exec', 'src']);
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    private function sanitizeAuth(array $auth): array
    {
        return $this->only($auth, [
            'type',
            'noauth',
            'apikey',
            'awsv4',
            'basic',
            'bearer',
            'digest',
            'edgegrid',
            'hawk',
            'ntlm',
            'oauth1',
            'oauth2',
        ]);
    }

    private function isFolder(array $item): bool
    {
        return isset($item['item']) && is_array($item['item']) && !isset($item['request']);
    }

    private function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }
}
