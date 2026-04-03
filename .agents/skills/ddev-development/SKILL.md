---
name: ddev-development
description: Use this skill whenever a repository contains a `.ddev` directory or the task involves DDEV. Route local Laravel development commands through DDEV, especially Composer, Artisan, npm, Node, PHPUnit, and related tooling. Use it for DDEV configuration, command execution, environment troubleshooting, and keeping host and container PHP/Node behavior aligned.
---

# DDEV Development

Use DDEV as the default command runner for this repository whenever a `.ddev` directory exists.

## Command Policy

- Run Composer commands through DDEV.
- Run Artisan and PHP test commands through DDEV.
- Run npm and other Node-based commands through DDEV.
- Prefer container execution even when the same command would work on the host.
- Treat host-level `composer`, `php artisan`, `php`, `npm`, and `node` commands as exceptions that require a clear reason.

## Preferred Command Forms

- Use `ddev composer <args>` for Composer commands.
- Use `ddev exec php artisan <args>` for Artisan commands.
- Use `ddev exec php artisan test <args>` or `ddev exec ./vendor/bin/phpunit <args>` for PHP tests.
- Use `ddev npm <args>` for npm commands when available.
- Fall back to `ddev exec npm <args>` if needed.
- Use `ddev exec node <args>` for direct Node commands.

## Common Examples

```bash
ddev composer install --no-interaction --prefer-dist
ddev composer update --no-interaction --prefer-dist --with-all-dependencies
ddev exec php artisan route:list
ddev exec php artisan make:test --phpunit ExampleTest --no-interaction
ddev exec php artisan test --compact tests/Feature/ExampleTest.php
ddev npm run build
ddev npm run dev
```

## Workflow

1. Check whether `.ddev` exists before running local Composer, Artisan, npm, or Node commands.
2. Confirm the project is available in DDEV with `ddev describe` or `ddev start` if command execution depends on the container being up.
3. If a DDEV command fails because Docker, the daemon, or sandbox permissions are blocked, request DDEV/Docker access for the session and retry before switching approaches.
4. Do not treat a permission-related failure as proof that DDEV is unavailable until you have requested access and retried.
5. Keep command examples in code reviews, notes, and follow-up instructions in DDEV form so future agents copy the correct pattern.
6. Prefer DDEV when diagnosing environment issues because CI and container PHP versions are usually closer to production than the host machine.

## Exceptions

- Use host commands only when the task is explicitly about the host environment.
- Use host commands only when DDEV is unavailable after requested access was declined or after a retry confirms a non-permission DDEV failure, and you have stated that limitation.
- If a command must run outside DDEV, explain why before relying on it.
