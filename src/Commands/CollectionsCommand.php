<?php

namespace BagherKeshmiri\PostmanSync\Commands;

use BagherKeshmiri\PostmanSync\PostmanApi;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class CollectionsCommand extends Command
{
    protected $signature = 'postman:collections';

    protected $description = 'List the collections the API key can see, to find the uid to configure';

    public function handle(PostmanApi $api): int
    {
        if (blank(config('postman.key'))) {
            $this->error('Set POSTMAN_API_KEY in .env first.');

            return CommandAlias::FAILURE;
        }

        try {
            // the uid is needed before one can be configured, so ask for the listing with the key alone
            $collections = (new PostmanApi(config('postman.key'), 'listing-only'))->collections();
        } catch (Throwable $th) {
            $this->error($th->getMessage());

            return CommandAlias::FAILURE;
        }

        if ($collections === []) {
            $this->warn('The key can see no collections.');

            return CommandAlias::SUCCESS;
        }

        $current = config('postman.collection_uid');

        $this->table(
            ['name', 'uid', ''],
            array_map(
                fn(string $name, string $uid) => [$name, $uid, $uid === $current ? '<- configured' : ''],
                array_keys($collections),
                array_values($collections),
            ),
        );

        $this->line('Copy the uid of the collection you want into POSTMAN_COLLECTION_UID.');

        return CommandAlias::SUCCESS;
    }
}
