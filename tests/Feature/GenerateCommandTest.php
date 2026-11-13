<?php

namespace YasinTgh\LaravelPostman\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use YasinTgh\LaravelPostman\Tests\Fixtures\UserController;
use YasinTgh\LaravelPostman\Tests\TestCase;

class GenerateCommandTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = sys_get_temp_dir() . '/laravel-postman-' . uniqid();
        config(['postman.output.path' => $this->outputDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputDir);

        parent::tearDown();
    }

    public function test_it_writes_a_json_collection_and_reports_skipped_routes(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/broken', [UserController::class, 'missing']);

        $this->artisan('postman:generate')
            ->expectsOutputToContain('Skipped [GET|HEAD] api/broken')
            ->expectsOutputToContain('1 route(s) documented, 1 skipped.')
            ->assertSuccessful();

        $file = $this->outputDir . '/api_collection.json';
        $this->assertFileExists($file);

        $collection = json_decode(file_get_contents($file), true);
        $this->assertSame('{{base_url}}/api/users', $collection['item'][0]['item'][0]['request']['url']['raw']);
    }
}
