<?php

namespace BagherKeshmiri\PostmanSync\Tests\Fixtures;

class Collection
{
    /** A collection as Postman exports it: two live requests, one stale, a saved example, typed-in values. */
    public static function make(): array
    {
        return [
            'info' => [
                'name' => 'Demo',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [
                [
                    'name' => 'Users',
                    'item' => [
                        [
                            'name' => 'index',
                            'request' => [
                                'method' => 'GET',
                                'header' => [['key' => 'Accept', 'value' => 'application/json']],
                                'url' => self::url(['users']),
                            ],
                            'response' => [['name' => '200 ok', 'body' => '{"data":[]}']],
                        ],
                        [
                            'name' => 'store',
                            'request' => [
                                'method' => 'POST',
                                'header' => [],
                                'url' => self::url(['users']),
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => '{"name":"Elnaz"}',
                                    'options' => ['raw' => ['language' => 'json']],
                                ],
                            ],
                            'response' => [],
                        ],
                    ],
                ],
                [
                    'name' => 'Legacy',
                    'item' => [
                        [
                            'name' => 'destroy',
                            'request' => ['method' => 'DELETE', 'header' => [], 'url' => self::url(['users', 'legacy'])],
                            'response' => [],
                        ],
                    ],
                ],
            ],
            'variable' => [['key' => 'base_url', 'value' => 'http://localhost/api']],
        ];
    }

    private static function url(array $path): array
    {
        return [
            'raw' => '{{base_url}}/' . implode('/', $path),
            'host' => ['{{base_url}}'],
            'path' => $path,
        ];
    }
}
