<?php

namespace BagherKeshmiri\PostmanSync\Commands;

use BagherKeshmiri\PostmanSync\PostmanApi;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SyncCommand extends Command
{
    protected $signature = 'postman:sync
                            {--file= : Collection to sync, defaults to the configured one}
                            {--no-publish : Update the file only, do not upload}
                            {--dry-run : Report both steps without writing or uploading}
                            {--force : Publish even when routes and collection disagree}';

    protected $description = 'Build the collection from the routes and publish it to the Postman workspace';

    public function handle(PostmanApi $api): int
    {
        $options = array_filter(['--file' => $this->option('file'), '--dry-run' => $this->option('dry-run')]);

        // the routes are the source of truth, so the file is always rebuilt before it goes up
        if (!$this->step('1/2  build from routes', 'postman:build', $options)) {
            return CommandAlias::FAILURE;
        }

        if ($this->option('no-publish') || !$api->configured()) {
            $this->newLine();
            $this->info($api->configured()
                ? 'File is up to date. Nothing uploaded because of --no-publish.'
                : 'File is up to date. Set POSTMAN_API_KEY and POSTMAN_COLLECTION_UID to upload it.');

            return CommandAlias::SUCCESS;
        }

        $published = $this->step(
            '2/2  publish to Postman',
            'postman:publish',
            $options + array_filter(['--force' => $this->option('force')]),
        );

        return $published ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
    }

    private function step(string $title, string $command, array $options): bool
    {
        $this->newLine();
        $this->line("<fg=cyan>$title</>");

        return $this->call($command, $options) === CommandAlias::SUCCESS;
    }
}
