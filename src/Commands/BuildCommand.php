<?php

namespace BagherKeshmiri\PostmanSync\Commands;

use BagherKeshmiri\PostmanSync\BuildReport;
use BagherKeshmiri\PostmanSync\CollectionBuilder;
use BagherKeshmiri\PostmanSync\CollectionFile;
use BagherKeshmiri\PostmanSync\RouteMap;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class BuildCommand extends Command
{
    protected $signature = 'postman:build
                            {--file= : Collection to build, defaults to the configured one}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Bring the collection file in line with the routes and their validation rules';

    public function handle(RouteMap $routes, CollectionBuilder $builder): int
    {
        $path = $this->option('file') ?: config('postman.file');

        try {
            $collection = CollectionFile::load($path);
        } catch (Throwable $th) {
            $this->error($th->getMessage());

            return CommandAlias::FAILURE;
        }

        $report = new BuildReport;
        $collection = $builder->build($collection, $routes->endpoints(), $routes->rules(), $report);

        $this->render($report);

        if ($this->option('dry-run')) {
            $this->line('DRY RUN - nothing written.');

            return CommandAlias::SUCCESS;
        }

        CollectionFile::save($path, $collection);
        $this->info("Written to $path");

        return CommandAlias::SUCCESS;
    }

    private function render(BuildReport $report): void
    {
        $this->line(sprintf('endpoints    : %d', $report->endpoints()));
        $this->line(sprintf('kept         : %d', count($report->kept)));
        $this->line(sprintf('added        : %d', count($report->added)));
        $this->line(sprintf('removed      : %d', count($report->removed)));
        $this->line(sprintf('query params : %d request(s) changed', $report->queries));
        $this->line(sprintf('json bodies  : %d request(s) changed', $report->bodies));
        $this->line(sprintf('variables    : %s', implode(', ', $report->variables) ?: '-'));

        foreach (array_slice($report->added, 0, 25) as $key) {
            $this->info("  added   : $key");
        }

        foreach (array_slice($report->removed, 0, 25) as $key) {
            $this->warn("  removed : $key");
        }

        if (!$report->changed()) {
            $this->info('Collection already matches the routes.');
        }
    }
}
