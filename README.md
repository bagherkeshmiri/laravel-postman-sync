# Laravel Postman Sync

[![Latest version on Packagist](https://img.shields.io/packagist/v/bagherkeshmiri/laravel-postman-sync.svg?style=flat-square)](https://packagist.org/packages/bagherkeshmiri/laravel-postman-sync)
[![Tests](https://img.shields.io/github/actions/workflow/status/bagherkeshmiri/laravel-postman-sync/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/bagherkeshmiri/laravel-postman-sync/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/bagherkeshmiri/laravel-postman-sync/php?style=flat-square)](https://packagist.org/packages/bagherkeshmiri/laravel-postman-sync)
[![Total downloads](https://img.shields.io/packagist/dt/bagherkeshmiri/laravel-postman-sync.svg?style=flat-square)](https://packagist.org/packages/bagherkeshmiri/laravel-postman-sync)
[![License](https://img.shields.io/packagist/l/bagherkeshmiri/laravel-postman-sync.svg?style=flat-square)](LICENSE)

Keep a Postman collection in step with your Laravel routes: the routes decide which requests
exist and which fields they carry, and one command publishes the result to your workspace.

The direction is one way, **code → Postman**. Your API changes in the code first, so that is the
source of truth; anything edited directly inside Postman is replaced on the next publish.

What makes it usable on a real project is the second half of that sentence: a run rewrites only
what the routes describe. Saved example responses, authentication, scripts, headers, folder names
and the values your team typed into request bodies are all carried through untouched.

```bash
php artisan postman:sync
```

## Contents

- [Why](#why)
- [What it looks like](#what-it-looks-like)
- [Installation](#installation)
- [Getting your credentials](#getting-your-credentials)
- [The collection file](#the-collection-file)
- [Commands](#commands)
- [What a build changes, and what it never touches](#what-a-build-changes-and-what-it-never-touches)
- [Where the fields come from](#where-the-fields-come-from)
- [How requests are named and placed](#how-requests-are-named-and-placed)
- [Configuration](#configuration)
- [Using it in CI](#using-it-in-ci)
- [How it works](#how-it-works)
- [Limitations](#limitations)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Why

An API collection rots the moment someone adds a route. The usual answers are to regenerate the
whole collection — which throws away every saved response and every environment value the team
built up — or to update it by hand, which nobody does.

This package takes the middle path. It treats the collection file as something that lives in your
repository and gets *edited*, not replaced: routes and validation rules decide the shape, and
everything else is left exactly where it was found. Because the file is in the repository, a
collection that has drifted from the code shows up in a diff like any other stale artefact.

## What it looks like

Given these routes:

```php
Route::get('api/articles', [ArticleController::class, 'index']);
Route::post('api/articles', [ArticleController::class, 'store']);
Route::get('api/articles/{article}', [ArticleController::class, 'show']);
```

with this form request on the `store` action:

```php
class StoreArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'published_at' => 'nullable|date',
            'author_id'    => 'required|integer|exists:users,id',
            'tags'         => 'array',
            'tags.*'       => 'string',
            'cover'        => 'nullable|image',
        ];
    }
}

// and an inline one on index()
$request->validate([
    'search'   => 'nullable|string',
    'per_page' => 'required|integer',
]);
```

`php artisan postman:sync` produces an `Articles` folder holding `index`, `store` and `show`. The
`store` request gets this body, and `{{article_id}}` is declared as a collection variable:

```json
{
    "title": "",
    "published_at": "2026-09-19",
    "author_id": 1,
    "tags": [
        ""
    ]
}
```

`cover` is absent, because an upload cannot be expressed in a JSON body. `index` becomes
`{{base_url}}/articles?per_page=0`, with `search` present but disabled since it is optional, and
each parameter carrying its rule as its description.

Run it again after adding a field, and only that field appears — the title you typed into the
request last week is still there.

## Installation

PHP 8.2+ and Laravel 10, 11, 12 or 13.

```bash
composer require --dev bagherkeshmiri/laravel-postman-sync
php artisan vendor:publish --tag=postman-config
```

`--dev` is the usual choice, since nothing here runs at request time. Drop it if you publish the
collection from a production deploy.

The service provider is discovered automatically.

## Getting your credentials

In `.env`:

```env
POSTMAN_API_KEY=
POSTMAN_COLLECTION_UID=
```

- **API key** — Postman → your avatar → *Account settings* → *API keys* → *Generate*.
- **Collection uid** — set the key first, then run:

  ```bash
  php artisan postman:collections
  ```

  and copy the uid of the collection you want. It looks like `<user-id>-<collection-id>`, and the
  one you already have configured is marked in the listing.

Without these two values `postman:build` still works; only publishing needs them.

## The collection file

The collection lives in your repository at the path in `postman.file`
(`postman/collection.json` by default) and must already exist.

**If you already have a collection**, export it from Postman once (*Collection → … → Export*,
v2.1 schema) and save it there. Starting from an export means your folder structure,
authentication, scripts and saved example responses are kept from the first run.

**If you are starting from scratch**, an empty collection is enough — create the file by hand:

```json
{
    "info": {
        "name": "My API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": []
}
```

The first build fills it from your routes and declares the variables it used, including
`base_url`. Set the values of those variables in Postman, or in the file, once.

Commit the collection file. Its diff is the point.

## Commands

| Command | What it does |
|---|---|
| `postman:build` | Rewrites the file from the routes |
| `postman:publish` | Uploads the file, refusing if it does not match the routes |
| `postman:sync` | Both, in that order |
| `postman:collections` | Lists the collections your key can see |

| Option | On | Meaning |
|---|---|---|
| `--file=` | build, publish, sync | Work on a collection other than the configured one |
| `--dry-run` | build, publish, sync | Report only: nothing is written, nothing is uploaded |
| `--no-publish` | sync | Update the file and stop |
| `--force` | publish, sync | Upload even when the routes and the file disagree |

A typical run:

```bash
php artisan postman:sync --dry-run   # see what would change
php artisan postman:sync             # build the file, then publish it
git add postman/collection.json      # and commit the diff
```

`postman:build` prints a summary of what it did:

```
endpoints    : 42
kept         : 39
added        : 3
removed      : 1
query params : 4 request(s) changed
json bodies  : 6 request(s) changed
variables    : article_id
  added   : GET articles/{param}  [Articles]
  removed : DELETE users/legacy
```

`postman:sync` skips the upload, with a note rather than an error, when no API key is configured —
so it is safe to run on a machine that has none.

## What a build changes, and what it never touches

Each run:

- a **new route** becomes a new request, placed beside the requests sharing the longest path
  prefix with it, so an existing folder structure is respected
- a **deleted route** has its request removed, and a folder left empty by that removal is removed too
- a **new field** in a `FormRequest` is added to the JSON body — values already typed into the
  other fields are kept
- a **removed field** disappears from the body
- a **new query rule** is added, enabled when the rule is `required` and disabled otherwise, with
  the rule itself as the parameter description
- any `{{variable}}` a request mentions is declared on the collection if it was missing, with an
  empty value; a variable that already has a value keeps it

Everything else in the file is carried through untouched: **saved example responses**, headers,
authentication, scripts, folder names, collection variable values, and any request body that is
not valid JSON (assumed hand-written).

A build is idempotent. Running it twice over its own output reports no changes.

`postman:publish` compares the file against the routes before uploading and refuses when they
disagree, so a stale file cannot silently overwrite your workspace. Postman has no merge endpoint:
a publish replaces the whole collection, which is why the check exists.

## Where the fields come from

For each route, the controller action is reflected to find a `FormRequest` parameter and its
`rules()` are read. When there is none, an inline `$request->validate([...])` in the method body is
parsed instead. A `rules()` that needs a live request is skipped rather than guessed at.

A `GET` turns its rules into query parameters; everything else turns them into a JSON body.

Dotted and starred keys become a nested skeleton, so `items.*.price` produces:

```json
{ "items": [ { "price": 0 } ] }
```

In a query string, a dotted key becomes bracket notation (`filter.status` → `filter[status]`) and
starred keys are skipped, since `date.*` is already covered by `date`.

Values are a placeholder of the right type, chosen from the rule:

| Rule contains | Value |
|---|---|
| `boolean` | `false` |
| `array` | `[]` |
| `date` | today's date |
| `email` | `user@example.com` |
| `integer` / `numeric` | `1` for a field ending in the parameter suffix, `0` otherwise |
| anything else | `""` |

Fields validated as `image`, `file` or `mimes` are skipped: a JSON body cannot express an upload,
so build those requests as `form-data` in Postman and they will be left alone.

## How requests are named and placed

A new request is named after what it does, following the resource convention:

| Path ends in | Method | Name |
|---|---|---|
| a route parameter | `GET` | `show` |
| a route parameter | `PUT` / `PATCH` | `update` |
| a route parameter | `DELETE` | `destroy` |
| a plain segment, and a `GET .../{param}` sibling exists | `GET` | `index` |
| a plain segment, and a `GET .../{param}` sibling exists | `POST` | `store` |
| a plain segment | anything else | that segment, e.g. `restore` |

It is placed in the folder holding the request that shares the longest path prefix with it. When
nothing shares at least two leading segments, it falls back to folders named after its own path —
`api/projects/{project}/tasks` lands in `Projects / Tasks`, under `root_folder` if one is set.

Route parameters become collection variables: `{user}` is written as `{{user_id}}`, using
`parameter_suffix`, with `parameter_variables` for the exceptions. A binding like `{user:username}`
uses the parameter name, not the column.

## Configuration

`config/postman.php`:

| Key | Default | Meaning |
|---|---|---|
| `key` | `env('POSTMAN_API_KEY')` | Postman API key |
| `collection_uid` | `env('POSTMAN_COLLECTION_UID')` | Which collection to publish to |
| `file` | `base_path('postman/collection.json')` | The collection in your repository |
| `route_prefix` | `'api'` | Only these routes are documented; the prefix is stripped from request paths |
| `base_url_variable` | `'base_url'` | Requests are written against `{{this}}` |
| `parameter_suffix` | `'_id'` | `{user}` becomes `{{user_id}}` |
| `parameter_variables` | `[]` | Exceptions to that rule, e.g. `'view' => 'view_id'` |
| `root_folder` | `''` | Where a request with no obvious home goes; empty means the collection root |

Documenting a versioned API is a matter of the prefix and the base url:

```php
'route_prefix'      => 'api/v2',   // only v2 routes, and "api/v2/" stripped from the paths
'base_url_variable' => 'base_url', // {{base_url}} then means https://example.test/api/v2
```

## Using it in CI

Because the collection is a file in the repository, a stale one is a diff. Fail the build when
someone changes a route without rebuilding:

```yaml
- name: The Postman collection is up to date
  run: |
    php artisan postman:build
    git diff --exit-code postman/collection.json
```

And publish from your deploy job, where the API key lives in a secret:

```yaml
- name: Publish the collection
  env:
    POSTMAN_API_KEY: ${{ secrets.POSTMAN_API_KEY }}
    POSTMAN_COLLECTION_UID: ${{ secrets.POSTMAN_COLLECTION_UID }}
  run: php artisan postman:publish
```

`postman:publish` exits non-zero when the file does not match the routes, so the deploy fails
rather than overwriting your workspace with something stale.

## How it works

Four pieces, each with one job:

| Class | Responsibility |
|---|---|
| `Shape` | Collapses a Laravel uri and a Postman url to the same string, so the two sides can be compared |
| `RouteMap` | What the application says its API is: which endpoints exist, and which fields each validates |
| `CollectionBuilder` | Rewrites the collection to match, leaving everything else as it found it |
| `PostmanApi` | The two calls the Postman API needs: list collections, replace one |

`Shape` is the idea the rest hangs on. A route `api/providers/{provider}/orders` and a Postman url
`{{base_url}}/providers/{{provider_id}}/orders` describe one endpoint, but nothing in either string
says so. Dropping the prefix and collapsing every parameter — `{provider}`, `{{provider_id}}`, a
literal `17` — to `{param}` makes them equal strings, and from there a build is set arithmetic over
`METHOD path` keys: what is in the routes and not the collection gets added, what is in the
collection and not the routes gets removed, and what is in both is left alone apart from its
fields.

## Limitations

- **One way.** Nothing reads from Postman and writes into your codebase.
- **JSON bodies only.** `form-data`, uploads and `x-www-form-urlencoded` are not generated; build
  those by hand and they will be preserved.
- **Only `Controller@method` actions** contribute fields. Closure routes still get a request, just
  an empty one.
- **`rules()` that needs a live request** — one that reads `$this->route()` or `$this->user()` —
  is skipped rather than guessed at.
- **Inline `$request->validate([...])`** is read from the method source, so it must be a literal
  array of string or array rules. A variable, a merged array or a call is not followed.
- **`Rule` objects** in a rule array are rendered as their class name in the description.
- The collection file **must exist**; the package edits, it does not create.

## Testing

```bash
composer install
composer test
```

The suite runs on [Testbench](https://packages.tools/testbench) — routes are registered in the
test itself and the Postman API is faked, so nothing reaches the network.

```bash
composer lint    # code style
composer format  # code style, applying the fixes
```

## Contributing

Pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). For security problems, please
follow [SECURITY.md](SECURITY.md) rather than opening a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

- [Bagher Keshmiri](https://github.com/bagherkeshmiri)
- [All contributors](https://github.com/bagherkeshmiri/laravel-postman-sync/graphs/contributors)

## License

MIT — see [LICENSE](LICENSE).
