# Laravel Postman Sync

Keep a Postman collection in step with your Laravel routes: the routes decide which requests
exist and which fields they carry, and one command publishes the result to your workspace.

The direction is one way, **code → Postman**. Your API changes in the code first, so that is
the source of truth; anything edited directly inside Postman is replaced on the next publish.

## Install

```bash
composer require topmenu/laravel-postman-sync
php artisan vendor:publish --tag=postman-config
```

Then in `.env`:

```env
POSTMAN_API_KEY=
POSTMAN_COLLECTION_UID=
```

- **API key** — Postman → your avatar → Account settings → API keys → Generate.
- **Collection uid** — set the key, then run `php artisan postman:collections` and copy the uid
  of the collection you want. It looks like `<user-id>-<collection-id>`.

The collection file must already exist. Export your collection from Postman once (v2.1 schema),
save it in the repository, and point `postman.file` at it. Starting from an exported file means
your folder structure, authentication, scripts and saved example responses are kept.

## Use

```bash
php artisan postman:sync --dry-run   # report only, nothing written or uploaded
php artisan postman:sync             # build the file, then publish it
```

Then commit the collection file.

| Command | What it does |
|---|---|
| `postman:build` | Rewrites the file from the routes |
| `postman:publish` | Uploads the file, refusing if it does not match the routes |
| `postman:sync` | Both, in that order |
| `postman:collections` | Lists the collections your key can see |

Options: `--file=` to work on a different collection, `--dry-run`, `--no-publish` (sync only),
`--force` (publish despite a mismatch).

## What it changes, and what it never touches

Each run:

- a **new route** becomes a new request, placed beside the requests sharing the longest path
  prefix with it, so an existing folder structure is respected
- a **deleted route** has its request removed
- a **new field** in a `FormRequest` is added to the JSON body — values already typed into the
  other fields are kept
- a **removed field** disappears from the body
- a **new query rule** is added, enabled when the rule is `required` and disabled otherwise, with
  the rule itself as the parameter description
- any `{{variable}}` a request mentions is declared on the collection if it was missing

Everything else in the file is carried through untouched: **saved example responses**, headers,
authentication, scripts, folder names, collection variable values, and any request body that is
not valid JSON (assumed hand-written).

Fields validated as `image`, `file` or `mimes` are skipped, since a JSON body cannot express an
upload — build those requests as `form-data` in Postman.

`postman:publish` compares the file against the routes before uploading and refuses when they
disagree, so a stale file cannot silently overwrite your workspace.

## Where the fields come from

For each route, the controller action is reflected to find a `FormRequest` parameter, and its
`rules()` are read. When there is none, an inline `$request->validate([...])` in the method body
is parsed instead. A `rules()` that needs a live request is skipped rather than guessed at.

## Configuration

```php
'file'                 // the collection in your repository
'route_prefix'         // only these routes are documented; stripped from request paths
'base_url_variable'    // requests are written against {{this}}
'parameter_suffix'     // {user} becomes {{user_id}}
'parameter_variables'  // exceptions to that rule
'root_folder'          // where a request with no obvious home goes
```

## Requirements

PHP 8.2+, Laravel 10–13.

## License

MIT
