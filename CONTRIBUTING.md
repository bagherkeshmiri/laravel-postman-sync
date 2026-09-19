# Contributing

Thanks for taking the time. Bug reports, ideas and pull requests are all welcome.

## Reporting a bug

Open an issue with the Laravel and PHP version, the relevant part of your `config/postman.php`,
the route definition that misbehaves, and what `php artisan postman:build --dry-run` printed.
A minimal collection file that reproduces the problem is worth more than any description.

## Working on the code

```bash
git clone https://github.com/bagherkeshmiri/laravel-postman-sync.git
cd laravel-postman-sync
composer install
composer test
```

The test suite runs on [Testbench](https://packages.tools/testbench), so no application is needed:
routes are registered in the test itself and the Postman API is faked with `Http::fake()`. No test
should ever reach the network.

Before opening a pull request:

```bash
composer lint   # code style, non-destructive
composer format # code style, applies the fixes
composer test
```

## What a good pull request looks like

- One concern per pull request.
- A test that fails before the change and passes after it.
- No new dependency unless there is no reasonable way around it.
- Code style is [Pint](https://laravel.com/docs/pint) with the `laravel` preset; run `composer format`
  rather than hand-formatting.
- Comments explain *why* something is done, never *what* the line does. Most code needs none.

## Things worth knowing about the design

- The sync is one way, code → Postman. Anything that would read from Postman and write into the
  codebase is out of scope.
- A run must never destroy work that the routes do not describe: saved example responses, auth,
  scripts, headers and typed-in values are carried through untouched. New behaviour has to keep
  that property, and a test should assert it.
- `Shape` is what makes the two sides comparable. A Laravel uri and a Postman url collapse to the
  same string, and everything else in the package compares endpoints through it.

## Security

Please do not open a public issue for a security problem — see [SECURITY.md](SECURITY.md).
