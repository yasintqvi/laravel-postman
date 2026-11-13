<?php

namespace YasinTgh\LaravelPostman\Tests\Feature;

use Illuminate\Support\Facades\Route;
use YasinTgh\LaravelPostman\Collections\Builder;
use YasinTgh\LaravelPostman\Collections\RouteGrouper;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;
use YasinTgh\LaravelPostman\Services\RouteAnalyzer;
use YasinTgh\LaravelPostman\Tests\Fixtures\PostController;
use YasinTgh\LaravelPostman\Tests\Fixtures\UserController;
use YasinTgh\LaravelPostman\Tests\TestCase;

class CollectionBuildTest extends TestCase
{
    private function build(array $overrides = []): array
    {
        $config = $this->postmanConfig($overrides);
        $routes = (new RouteAnalyzer($this->app['router'], $config))->analyze();

        $grouper = new RouteGrouper(
            $config['structure']['folders']['strategy'],
            $config,
            new NameGenerator($config),
            new RequestBodyGenerator(),
            $config['structure']['requests']['default_values'] ?? [],
        );

        return (new Builder($grouper, $config))->build($routes);
    }

    /** Folder tree as "folder/" and request names, indented by depth. */
    private function tree(array $items, int $depth = 0): array
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = str_repeat('  ', $depth) . $item['name'] . (isset($item['request']) ? '' : '/');
            if (isset($item['item'])) {
                array_push($lines, ...$this->tree($item['item'], $depth + 1));
            }
        }

        return $lines;
    }

    private function findRequest(array $items, string $method): ?array
    {
        foreach ($items as $item) {
            if (($item['request']['method'] ?? null) === $method) {
                return $item;
            }
            if (isset($item['item']) && $found = $this->findRequest($item['item'], $method)) {
                return $found;
            }
        }

        return null;
    }

    public function test_nested_path_keeps_deep_and_parameter_only_routes(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/a/b/c', [UserController::class, 'index']);
        Route::get('api/{locale}', [UserController::class, 'index']);
        Route::get('api/rapid/x', [UserController::class, 'index']);

        $collection = $this->build(['structure' => ['folders' => ['strategy' => 'nested_path', 'max_depth' => 2]]]);

        $this->assertSame([
            'users/',
            '  [GET] api/users',
            'a/',
            '  b/',
            '    [GET] api/a/b/c',
            '[GET] api/{locale}',
            'rapid/',
            '  x/',
            '    [GET] api/rapid/x',
        ], $this->tree($collection['item']));
    }

    public function test_prefix_strategy_groups_by_first_static_segment(): void
    {
        Route::get('api/{locale}', [UserController::class, 'index']);
        Route::get('api/{locale}/pages', [UserController::class, 'index']);
        Route::get('api/v1/users', [UserController::class, 'index']);

        $folders = fn(array $overrides) => array_column($this->build($overrides)['item'], 'name');
        $prefixStrategy = ['structure' => ['folders' => ['strategy' => 'prefix']]];

        $this->assertSame(['other', 'pages', 'v1'], $folders($prefixStrategy));
        $this->assertSame(['users'], $folders($prefixStrategy + ['routes' => ['prefix' => 'api/v1']]));
    }

    public function test_controller_strategy_names_closure_folder_and_strips_suffix_only(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/ping', fn() => 'pong');

        $folders = array_column($this->build(['structure' => ['folders' => ['strategy' => 'controller']]])['item'], 'name');

        $this->assertSame(['User', 'Closures'], $folders);
    }

    public function test_path_variables_drop_optional_and_binding_markers(): void
    {
        Route::get('api/posts/{post:slug}/{comment?}', [UserController::class, 'index']);

        $request = $this->findRequest($this->build()['item'], 'GET')['request'];

        $this->assertSame('{{base_url}}/api/posts/:post/:comment', $request['url']['raw']);
        $this->assertSame(['post', 'comment'], array_column($request['url']['variable'], 'key'));
    }

    public function test_form_request_with_route_user_and_injected_rules_gets_a_body(): void
    {
        Route::put('api/posts/{post}', [PostController::class, 'update']);

        $body = json_decode($this->findRequest($this->build()['item'], 'PUT')['request']['body']['raw'], true);

        $this->assertSame(['title', 'slug', 'year', 'owner'], array_keys($body));
        $this->assertLessThanOrEqual(2026, $body['year']);
    }

    public function test_content_type_only_sent_with_raw_bodies(): void
    {
        Route::put('api/posts/{post}', [PostController::class, 'update']);
        Route::get('api/users', [UserController::class, 'index']);

        $items = $this->build()['item'];
        $keys = fn(string $method) => array_column($this->findRequest($items, $method)['request']['header'], 'key');

        $this->assertContains('Content-Type', $keys('PUT'));
        $this->assertNotContains('Content-Type', $keys('GET'));
    }

    public function test_no_request_auth_when_auth_is_disabled(): void
    {
        Route::get('api/users', [UserController::class, 'index'])->middleware('auth:api');

        $collection = $this->build(['auth' => ['enabled' => false]]);

        $this->assertArrayNotHasKey('auth', $this->findRequest($collection['item'], 'GET')['request']);
        $this->assertArrayNotHasKey('auth', $collection);
    }

    public function test_api_key_auth_uses_config_and_is_not_duplicated_as_header(): void
    {
        Route::get('api/users', [UserController::class, 'index'])->middleware('auth:api');

        $collection = $this->build(['auth' => [
            'enabled' => true,
            'type' => 'api_key',
            'location' => 'query',
            'default' => ['key_name' => 'X-MY-KEY'],
        ]]);

        $this->assertSame(
            ['key' => 'X-MY-KEY', 'value' => '{{api_key}}', 'in' => 'query'],
            array_column($collection['auth']['apikey'], 'value', 'key'),
        );

        $request = $this->findRequest($collection['item'], 'GET')['request'];
        $this->assertSame('apikey', $request['auth']['type']);
        $this->assertNotContains('X-MY-KEY', array_column($request['header'], 'key'));
    }
}
