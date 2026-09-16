<?php

namespace TopMenu\PostmanSync;

use Illuminate\Support\ServiceProvider;
use TopMenu\PostmanSync\Commands\BuildCommand;
use TopMenu\PostmanSync\Commands\CollectionsCommand;
use TopMenu\PostmanSync\Commands\PublishCommand;
use TopMenu\PostmanSync\Commands\SyncCommand;

class PostmanSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');

        $this->app->bind(Shape::class, fn($app) => new Shape(trim((string) $app['config']->get('postman.route_prefix'), '/')));

        $this->app->bind(RouteMap::class, fn($app) => new RouteMap(
            $app->make(Shape::class),
            trim((string) $app['config']->get('postman.route_prefix'), '/'),
        ));

        $this->app->bind(CollectionBuilder::class, fn($app) => new CollectionBuilder(
            $app->make(Shape::class),
            (string) $app['config']->get('postman.base_url_variable'),
            (string) $app['config']->get('postman.parameter_suffix'),
            (array) $app['config']->get('postman.parameter_variables'),
            (string) $app['config']->get('postman.root_folder'),
            trim((string) $app['config']->get('postman.route_prefix'), '/'),
        ));

        $this->app->bind(PostmanApi::class, fn($app) => new PostmanApi(
            $app['config']->get('postman.key'),
            $app['config']->get('postman.collection_uid'),
        ));
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__ . '/../config/postman.php' => config_path('postman.php')], 'postman-config');

        $this->commands([
            BuildCommand::class,
            PublishCommand::class,
            SyncCommand::class,
            CollectionsCommand::class,
        ]);
    }
}
