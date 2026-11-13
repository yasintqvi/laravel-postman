<?php

namespace YasinTgh\LaravelPostman\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YasinTgh\LaravelPostman\Services\CollectionMerger;
use YasinTgh\LaravelPostman\Services\CollectionSanitizer;

class CollectionMergerTest extends TestCase
{
    private function request(string $method, string $path, array $extra = []): array
    {
        return array_merge([
            'name' => "{$method} {$path}",
            'request' => ['method' => $method, 'url' => ['raw' => '{{base_url}}/' . $path]],
        ], $extra);
    }

    private function remote(): array
    {
        return ['info' => ['name' => 'Remote'], 'item' => [
            ['name' => 'users', 'id' => 'folder-1', 'item' => [
                $this->request('GET', 'api/users', ['id' => 'req-1', 'response' => [['name' => 'ok']]]),
                $this->request('DELETE', 'api/users/:id'),
            ]],
            ['name' => 'health', 'request' => ['method' => 'GET', 'url' => ['raw' => 'https://status.example.com/health']]],
        ]];
    }

    private function generated(): array
    {
        return ['item' => [['name' => 'users', 'item' => [
            $this->request('GET', 'api/users'),
            $this->request('POST', 'api/users'),
        ]]]];
    }

    private function merger(bool $prune = false): CollectionMerger
    {
        return new CollectionMerger([
            'routes' => ['prefix' => 'api'],
            'cloud' => ['merge' => ['prune_stale_routes' => $prune]],
        ]);
    }

    public function test_report_lists_added_updated_and_preserved_requests(): void
    {
        $merger = $this->merger();
        $merged = $merger->merge($this->remote(), $this->generated());

        $this->assertSame([
            'added' => ['POST:api/users'],
            'updated' => ['GET:api/users'],
            'preserved' => ['DELETE:api/users/:id', 'GET:health'],
            'removed' => [],
        ], $merger->lastReport());

        $this->assertSame('req-1', $merged['item'][0]['item'][0]['id']);
        $this->assertSame([['name' => 'ok']], $merged['item'][0]['item'][0]['response']);
    }

    public function test_prune_removes_only_stale_generated_requests(): void
    {
        $merger = $this->merger(prune: true);
        $merger->merge($this->remote(), $this->generated());

        $this->assertSame(['DELETE:api/users/:id'], $merger->lastReport()['removed']);
        $this->assertSame(['GET:health'], $merger->lastReport()['preserved']);
    }

    public function test_manual_request_auth_is_kept_over_generated_noauth(): void
    {
        $manualAuth = ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{admin_token}}']]];

        $remote = ['item' => [$this->request('GET', 'api/users')]];
        $remote['item'][0]['request']['auth'] = $manualAuth;

        $generated = ['item' => [$this->request('GET', 'api/users'), $this->request('GET', 'api/posts')]];
        foreach ($generated['item'] as &$item) {
            $item['request']['auth'] = ['type' => 'noauth'];
        }
        unset($item);

        $merged = $this->merger()->merge($remote, $generated);

        $this->assertSame($manualAuth, $merged['item'][0]['request']['auth']);
        $this->assertSame(['type' => 'noauth'], $merged['item'][1]['request']['auth']);
    }

    public function test_folder_ids_survive_merge_and_sanitizing(): void
    {
        $merged = $this->merger()->merge($this->remote(), $this->generated());
        $sanitized = (new CollectionSanitizer())->sanitize($merged);

        $this->assertSame('folder-1', $sanitized['item'][0]['id']);
    }
}
