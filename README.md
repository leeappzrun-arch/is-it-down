# Is It Down

## Overview

Is It Down is a Laravel 13 and Livewire 4 application for managing monitored services and the recipients who should be notified about them. The project currently focuses on authenticated access, role-based administration, recipient management, service management, grouped routing targets, scheduled uptime checks, webhook and email delivery, user/account management, personal API keys, and a versioned REST API for integrations.

## Current Features

### Authenticated application shell

- Users sign in through Laravel Fortify.
- Verified users are routed to the dashboard.
- The dashboard shows a live service-status grid plus high-level totals for recipients, recipient groups, services, service groups, users, and API keys.
- Admins can open those dashboard stats to jump straight into the matching management screens.
- Authenticated users can access profile, appearance, and security settings.
- When Dave is enabled and configured by an admin, authenticated users also get a floating bottom-right chat launcher across the application shell.

### Security auditing

- Laravel Warden is installed for dependency and configuration audits.
- Local high-severity audits can be run through Composer scripts.
- GitHub Actions runs a Warden audit before PHPUnit in the main test workflow.

### Role-based access

- `admin` users can manage recipients and users.
- `user` accounts can access the authenticated application and their own settings.
- The system prevents the last remaining admin from being downgraded.

### Recipient management

- Create, edit, and delete recipients.
- Search the Recipients page to quickly filter recipient rows and recipient groups.
- Choose `Email` or `Webhook` in the UI while the application stores `mailto://` and `webhook://` endpoints internally.
- Configure webhook authentication as:
  - none
  - bearer token
  - basic auth
  - custom header
- Assign recipients to one or more groups.
- Confirm recipient and group deletions in a modal before they are removed.

### Group management

- Create, rename, and delete recipient groups.
- Use groups to organise related recipients for future routing use cases.
- Confirm group deletion before it is applied.

### Service management

- Create, edit, and delete services.
- Search the Services page to filter both service cards and service group cards from one input.
- Configure a service name, URL, polling interval, and optional expectation using either plain text or a regular expression.
- Assign services to one or more service groups.
- Assign recipients and recipient groups directly to a service.
- Track the latest monitoring status, how long the service has been in that state, the last reason, the last check time, and a live next-check timer from the Services page.
- Mark a service as down when the response is not HTTP 200 or when a configured text or regex expectation does not match the response body.
- Notify assigned recipients only when the service changes state, so repeated down checks do not resend the same alert until the service recovers.
- Deliver email alerts to `mailto://` recipients and JSON payloads to `webhook://` recipients using the authentication method saved on each webhook recipient.
- Include outage duration in recovery notifications so emails and webhook consumers can see how long a service was down before it came back up.
- Email all admin users if any webhook delivery fails during a status-change notification.
- Review the effective recipients for a service, including whether each route is direct, comes from a recipient group, or is inherited through a service group.

### Service group management

- Create, rename, and delete service groups.
- Assign direct recipients and recipient groups to a service group.
- Reuse those service groups across multiple monitored services.
- Confirm service and service group deletion before it is applied.

### User management

- Admins can create users.
- Admins can search the Users page by name, email, or role.
- Admins can assign and update roles.
- Admins can delete standard users after confirming the action.
- Admin accounts cannot be deleted from the Users page.

### API key management

- Admins can create personal API keys that are always linked to the account that created them.
- Admins can search the API Keys page by key details, owners, permissions, and status.
- Keys support expiration presets of 6 months, 1 year, 2 years, or never.
- Keys support per-section `read` and `write` permissions, including the new `services` area.
- API keys are stored securely as hashes and the plain-text token is only shown once in a post-create modal.
- Keys can be revoked without removing the database record.

### Dave

- Admins can configure Dave from `/settings/ai-assistant`.
- The Dave settings link appears in the admin settings navigation.
- Dave stays hidden until an admin enables it and saves a provider URL, model, and API key.
- Dave appears as a floating chat launcher in the bottom-right corner of authenticated pages.
- Conversations stay open across closes and page navigation for the current browser session.
- Standard users can ask Dave for help with monitoring and outage questions.
- Admins can also ask Dave to create, update, and delete users, recipients, and services.
- The assistant rules and management tool guidance are centralized in `app/Support/AiAssistant/AiAssistantRules.php` and `app/Support/AiAssistant/AiAssistantToolExecutor.php`, which should be updated when new features or management flows are added.

### REST API

- Versioned API routes live under `/api/v1`.
- Requests authenticate with a bearer token using a stored API key hash.
- Expired, revoked, or unlinked API keys are rejected automatically.
- `recipients:*` permissions cover recipients and recipient groups.
- `services:*` permissions cover services and service groups.
- `users:*` permissions cover user listing and management.
- Listing endpoints support search plus resource-specific filtering where relevant.
- Creation endpoints reuse the same validation rules as the matching Livewire management forms.

### In-app documentation

- `/user-guide` contains user-facing guidance for the features currently available.
- `/api-documentation` documents the current REST API, its authentication requirements, permissions, and endpoint contracts.
- `/api-playground` provides an authenticated in-app playground for trying documented API endpoints with a supplied API key.
- `/webhook-documentation` documents the current webhook recipient setup, supported authentication modes, and the details that should stay aligned with future webhook delivery changes.

## Main Routes

- `/` redirects authenticated users to the dashboard and guests to the login page.
- `/dashboard` is the main post-login landing page and shows the current system totals.
- `/recipients` is the admin recipient and group management page.
- `/services` is the admin service and service group management page.
- `/users` is the admin user management page.
- `/api-keys` is the admin API key management page.
- `/settings/ai-assistant` is the admin Dave configuration page.
- `/api/v1/*` is the authenticated REST API surface for recipients, recipient groups, services, service groups, and users.
- `/settings/profile`, `/settings/appearance`, and `/settings/security` manage account preferences.
- `/user-guide`, `/api-documentation`, `/api-playground`, and `/webhook-documentation` provide internal documentation pages.

## API Key Permissions

Available API key permissions are defined in `config/api_keys.php`.

- Add each new app section to the `resources` array.
- Every listed resource automatically receives `read` and `write` permissions.
- When new sections or API capabilities ship, update this config, the relevant tests, the API routes, the endpoint catalog, and the documentation in the same change.

## Local Development

This repository includes `.ddev` and should be worked on through DDEV whenever Docker is available.

### Common commands

```bash
ddev start
ddev composer install --no-interaction --prefer-dist
ddev exec php artisan migrate --seed
ddev exec php artisan schedule:work
ddev exec php artisan test --compact
ddev exec env CACHE_STORE=file composer security:audit
ddev npm run dev
```

If DDEV is unavailable, host-side Artisan and test commands may still work, but DDEV remains the preferred workflow for this project.

### Scheduler setup

Monitoring checks are scheduled inside `routes/console.php` and run every 30 seconds through Laravel's scheduler.

For local development, keep the scheduler running with:

```bash
ddev exec php artisan schedule:work
```

For production, add a single cron entry that runs Laravel's scheduler every minute:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Seeded access

Running the database seeder provisions two verified accounts for local development:

- `admin@example.com` / `password`
- `user@example.com` / `password`

The seeder also creates sample recipient groups, recipients, and personal API keys so the dashboard, management screens, and API have representative data immediately.
It also seeds service groups and services so the routing views and monitoring status cards have meaningful examples on a fresh install.

## Testing

Run focused tests while developing:

```bash
ddev exec php artisan test --compact tests/Feature/DocumentationPagesTest.php
```

Run the Warden audit locally:

```bash
ddev exec env CACHE_STORE=file composer security:audit
```

Run the Warden audit with NPM checks:

```bash
ddev exec env CACHE_STORE=file composer security:audit:npm
```

Run the full test suite when needed:

```bash
ddev exec php artisan test --compact
```

## Git Hooks

To make Warden run before each commit, point Git at the repo-managed hooks once per clone:

```bash
git config core.hooksPath .githooks
```

The included pre-commit hook runs `ddev exec env CACHE_STORE=file composer security:audit`, which blocks commits when Warden reports high-severity issues.

## Documentation Maintenance

The following files are part of the project’s living documentation and should be updated whenever related behavior changes:

- `README.md` for setup, architecture, feature summaries, routes, and developer workflow
- `resources/views/pages/⚡user-guide.blade.php` for user-facing workflow instructions
- `resources/views/pages/⚡api-documentation.blade.php` for API capabilities, contracts, authentication, and examples
- `resources/views/pages/⚡api-playground.blade.php` for the interactive endpoint playground
- `resources/views/pages/⚡webhook-documentation.blade.php` for webhook recipient setup, authentication, payload expectations, and delivery guidance
- `config/api_keys.php` for the API key permission registry used by the admin UI and future API authorization
- `app/Support/ApiDocumentation.php` for the shared endpoint catalog that powers the docs and playground
- `app/Support/AiAssistant/AiAssistantRules.php` and `app/Support/AiAssistant/AiAssistantToolExecutor.php` for the assistant's built-in application rules and supported management actions

If a feature, route, role, workflow, UI label, setup step, or API behavior changes, update the relevant documentation files in the same change.
If webhook configuration, authentication, payload shape, retry behavior, or delivery semantics change, update the webhook documentation page in the same change.
If a new management area or top-level app feature is added, update the dashboard stats as part of the same change when it should be surfaced there.
If new functionality or permissions affect the API, update the API routes, endpoint catalog, playground, tests, and API documentation in the same change.
