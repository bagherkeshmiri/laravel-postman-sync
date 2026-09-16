<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Postman credentials
    |--------------------------------------------------------------------------
    |
    | The API key comes from Postman under Account settings -> API keys.
    | The collection uid is "<user-id>-<collection-id>"; list yours with
    | GET https://api.getpostman.com/collections using the same key.
    |
    */

    'key' => env('POSTMAN_API_KEY'),

    'collection_uid' => env('POSTMAN_COLLECTION_UID'),

    /*
    |--------------------------------------------------------------------------
    | Collection file
    |--------------------------------------------------------------------------
    |
    | The collection lives in your repository and is the thing that gets built
    | and published. It must already exist: export your collection from Postman
    | once (v2.1 schema) and save it here.
    |
    */

    'file' => base_path('postman/collection.json'),

    /*
    |--------------------------------------------------------------------------
    | Which routes belong in the collection
    |--------------------------------------------------------------------------
    |
    | Only routes whose uri starts with this prefix are documented. The prefix
    | is stripped from the request paths, since the base url variable carries it.
    |
    */

    'route_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Base url variable
    |--------------------------------------------------------------------------
    |
    | The collection variable every generated request is written against, e.g.
    | {{base_url}}/users/{{user_id}}.
    |
    */

    'base_url_variable' => 'base_url',

    /*
    |--------------------------------------------------------------------------
    | Route parameter to collection variable
    |--------------------------------------------------------------------------
    |
    | A route parameter becomes a collection variable named after it plus the
    | suffix below, so {user} becomes {{user_id}}. List the exceptions here.
    |
    */

    'parameter_suffix' => '_id',

    'parameter_variables' => [
        // 'view' => 'view_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder for requests with nowhere else to go
    |--------------------------------------------------------------------------
    |
    | A new request is placed beside the ones sharing the longest path prefix.
    | When nothing matches, it goes under this folder, followed by the first
    | segments of its own path. Leave empty to place it at the root.
    |
    */

    'root_folder' => '',

];
