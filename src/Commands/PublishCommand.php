<?php

namespace BagherKeshmiri\PostmanSync\Commands;

use BagherKeshmiri\PostmanSync\CollectionFile;
use BagherKeshmiri\PostmanSync\PostmanApi;
use BagherKeshmiri\PostmanSync\RouteMap;
use BagherKeshmiri\PostmanSync\Shape;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class PublishCommand extends Command
{
    protected $signature = 'postman:publish
                            {--file= : Collection to publish, defaults to the configured one}
                            {--dry-run : Check the collection against the routes without uploading}
                            {--force : Upload even when routes and collection disagree}';

    protected $description = 'Replace the collection in the Postman workspace with the one in the repository';

    public function handle(RouteMap $routes, Shape $shape, PostmanApi $api): int
    {
        $path = $this->option('file') ?: config('postman.file');

        try {
            $collection = CollectionFile::load($path);
        } catch (Throwable $th) {
            $this->error($th->getMessage());

            return CommandAlias::FAILURE;
        }

        $endpoints = $routes->endpoints();
        $requests = [];

        foreach (CollectionFile::requests($collection['item']) as $item) {
            $requests[$shape->ofItem($item)] = true;
        }

        $missing = array_diff(array_keys($endpoints), array_keys($requests));
        $stale = array_diff(array_keys($requests), array_keys($endpoints));

        $this->line(sprintf('routes: %d   requests: %d', count($endpoints), count($requests)));

        if ($missing !== [] || $stale !== []) {
            $this->warn(sprintf('%d route(s) missing from the collection, %d request(s) no longer routed.', count($missing), count($stale)));

            foreach (array_slice($missing, 0, 10) as $key) {
                $this->line("  missing: $key");
            }
            foreach (array_slice($stale, 0, 10) as $key) {
                $this->line("  stale  : $key");
            }

            if (!$this->option('force') && !$this->option('dry-run')) {
                $this->error('Refusing to publish a collection that does not match the routes. Run postman:build first, or use --force.');

                return CommandAlias::FAILURE;
            }
        } else {
            $this->info('Collection matches the routes exactly.');
        }

        if ($this->option('dry-run')) {
            return CommandAlias::SUCCESS;
        }

        try {
            $name = $api->replace($collection);
        } catch (Throwable $th) {
            $this->error($th->getMessage());

            return CommandAlias::FAILURE;
        }

        $this->info("Published \"$name\". Published documentation follows the collection, so it is up to date too.");

        return CommandAlias::SUCCESS;
    }
}
