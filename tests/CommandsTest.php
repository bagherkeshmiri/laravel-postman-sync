<?php

namespace BagherKeshmiri\PostmanSync\Tests;

use BagherKeshmiri\PostmanSync\CollectionFile;
use BagherKeshmiri\PostmanSync\Tests\Fixtures\Collection;
use BagherKeshmiri\PostmanSync\Tests\Fixtures\UserController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/postman-sync-' . getmypid() . '.json';
        CollectionFile::save($this->path, Collection::make());

        config()->set('postman.file', $this->path);
        config()->set('postman.key', 'test-key');
        config()->set('postman.collection_uid', '1234-5678');

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('api/users', [UserController::class, 'index']);
        $router->post('api/users', [UserController::class, 'store']);
    }

    #[Test]
    public function a_dry_run_build_reports_without_touching_the_file(): void
    {
        $before = file_get_contents($this->path);

        $this->artisan('postman:build', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame($before, file_get_contents($this->path));
    }

    #[Test]
    public function a_build_writes_the_routes_into_the_file(): void
    {
        $this->artisan('postman:build')->assertSuccessful();

        $written = CollectionFile::encode(CollectionFile::load($this->path));

        $this->assertStringNotContainsString('users/legacy', $written);
        $this->assertStringContainsString('user@example.com', $written);
    }

    #[Test]
    public function publishing_a_collection_that_does_not_match_the_routes_is_refused(): void
    {
        $this->artisan('postman:publish')
            ->expectsOutputToContain('Refusing to publish')
            ->assertFailed();

        Http::assertNothingSent();
    }

    #[Test]
    public function force_publishes_a_mismatched_collection_anyway(): void
    {
        Http::fake(['api.getpostman.com/*' => Http::response(['collection' => ['name' => 'Demo']])]);

        $this->artisan('postman:publish', ['--force' => true])->assertSuccessful();

        Http::assertSent(fn($request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.getpostman.com/collections/1234-5678'
            && $request->header('X-Api-Key') === ['test-key']);
    }

    #[Test]
    public function sync_builds_then_publishes(): void
    {
        Http::fake(['api.getpostman.com/*' => Http::response(['collection' => ['name' => 'Demo']])]);

        $this->artisan('postman:sync')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertStringNotContainsString('users/legacy', (string) file_get_contents($this->path));
    }

    #[Test]
    public function no_publish_stops_after_the_file_is_written(): void
    {
        $this->artisan('postman:sync', ['--no-publish' => true])
            ->expectsOutputToContain('Nothing uploaded')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_missing_collection_file_fails_with_its_path(): void
    {
        $this->artisan('postman:build', ['--file' => '/no/such/collection.json'])
            ->expectsOutputToContain('Collection not found')
            ->assertFailed();
    }

    #[Test]
    public function the_collections_listing_marks_the_configured_one(): void
    {
        Http::fake(['api.getpostman.com/collections' => Http::response([
            'collections' => [
                ['name' => 'Demo', 'uid' => '1234-5678'],
                ['name' => 'Other', 'uid' => '1234-9999'],
            ],
        ])]);

        $this->artisan('postman:collections')
            ->expectsOutputToContain('1234-5678')
            ->assertSuccessful();
    }
}
