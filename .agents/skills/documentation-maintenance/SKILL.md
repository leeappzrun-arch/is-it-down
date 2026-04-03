---
name: documentation-maintenance
description: Keep the repository's living documentation aligned with shipped behavior. Use when features, setup steps, routes, navigation, roles, workflows, or API capabilities change.
license: MIT
metadata:
  author: Lee Caine
---

# Documentation Maintenance

Use this skill whenever a change affects how the project is installed, navigated, operated, or integrated with.

## Documentation Targets

- `README.md` for technical overview, setup, routes, testing, and developer workflow
- `resources/views/pages/⚡user-guide.blade.php` for user-facing instructions
- `resources/views/pages/⚡api-documentation.blade.php` for API status, endpoints, auth, payloads, and examples

## Rules

1. Treat documentation updates as part of the same change, not follow-up work.
2. Update `README.md` when features, commands, roles, routes, or setup steps change.
3. Update the User Guide when the UI, navigation, permissions, or user workflows change.
4. Update the API documentation page when endpoints, authentication, request or response shapes, error handling, or integration steps change.
5. If no API exists yet, keep the API documentation page honest and explicitly marked as pending.
6. When a new app section or API surface is added, update `config/api_keys.php` so fresh read and write permissions become available for API keys in the same change.

## Expected Workflow

1. Identify which behaviors changed.
2. Update the relevant documentation files before finishing the task.
3. Keep wording specific to the current system state and avoid placeholder starter-kit language.
