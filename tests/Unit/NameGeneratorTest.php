<?php

namespace YasinTgh\LaravelPostman\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;
use YasinTgh\LaravelPostman\Services\NameGenerator;

class NameGeneratorTest extends TestCase
{
    private function routeName(string $format, RouteInfoDto $route): string
    {
        return (new NameGenerator(['structure' => ['naming_format' => $format]]))->generate($route);
    }

    public function test_controller_routes_use_the_configured_format(): void
    {
        $route = new RouteInfoDto('api/users', ['POST'], false, 'App\\Http\\Controllers\\UserController', 'store');

        $this->assertSame('POST App\\Http\\Controllers\\UserController@store', $this->routeName('{method} {controller}@{action}', $route));
    }

    public function test_closures_fall_back_to_method_and_uri_when_format_needs_a_controller(): void
    {
        $closure = new RouteInfoDto('api/ping', ['GET', 'HEAD'], false, null, 'Closure');

        $this->assertSame('[GET] api/ping', $this->routeName('{method} {controller}@{action}', $closure));
        $this->assertSame('GET api/ping', $this->routeName('{method} {uri}', $closure));
    }
}
