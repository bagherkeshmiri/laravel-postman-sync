# Changelog

All notable changes to `laravel-postman-sync` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-19

First public release.

### Added

- `postman:build` — rewrites a collection file from the application's routes: adds requests for
  new routes, removes requests whose route is gone, and fills JSON bodies and query parameters
  from the validation rules.
- `postman:publish` — uploads the collection file to a Postman workspace, refusing when the file
  and the routes disagree unless `--force` is given.
- `postman:sync` — build then publish, in that order.
- `postman:collections` — lists the collections the API key can see, to find the uid to configure.
- Validation rules read from a `FormRequest` type-hinted on the controller action, falling back to
  an inline `$request->validate([...])` in the method body.
- Dotted and starred rule keys (`items.*.price`) expanded into a nested JSON skeleton.
- Placement of a new request next to the requests sharing the longest path prefix with it, so an
  existing folder structure is respected.
- Preservation of everything the routes do not describe: saved example responses, headers, auth,
  scripts, folder names, collection variable values, and values already typed into request bodies.
- `--dry-run` on every command that writes or uploads.
- Configuration file publishable with `--tag=postman-config`.

[Unreleased]: https://github.com/bagherkeshmiri/laravel-postman-sync/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bagherkeshmiri/laravel-postman-sync/releases/tag/v1.0.0
