# Security Policy

## Supported versions

The latest released 1.x version receives security fixes.

## Reporting a vulnerability

Please report security problems privately through
[GitHub's private vulnerability reporting](https://github.com/bagherkeshmiri/laravel-postman-sync/security/advisories/new)
rather than in a public issue.

Include what the problem allows an attacker to do and the steps to reproduce it. You can expect an
acknowledgement within a few days, and a fix or an explanation of why it is not a vulnerability
before any public disclosure.

## A note on your API key

This package sends your Postman API key to `api.getpostman.com` and nowhere else. Keep it in `.env`,
out of version control, and prefer a key scoped to the workspace you actually publish to.
