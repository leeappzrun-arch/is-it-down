# Is It Down

## Overview

Is It Down is a Laravel 13 and Livewire 4 application for managing monitored services and the recipients who should be notified about them. The project currently focuses on authenticated access, role-based administration, recipient management, service management, grouped routing targets, user/account management, and pre-provisioned API keys for future integrations.

## Current Features

### Authenticated application shell

- Users sign in through Laravel Fortify.
- Verified users are routed to the dashboard.
- The dashboard shows high-level totals for recipients, recipient groups, services, service groups, users, and API keys.
- Admins can open those dashboard stats to jump straight into the matching management screens.
- Authenticated users can access profile, appearance, and security settings.

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

- Admins can create API keys for their own account or for named services.
- Admins can search the API Keys page by key details, owners, permissions, and status.
- Keys support expiration presets of 6 months, 1 year, 2 years, or never.
- Keys support per-section `read` and `write` permissions, including the new `services` area.
- API keys are stored securely as hashes and the plain-text token is only shown once in a post-create modal.
- Keys can be revoked without removing the database record.

### In-app documentation

- `/user-guide` contains user-facing guidance for the features currently available.
- `/api-documentation` explains the current pre-API state and notes that full endpoint documentation will be added once the API exists.
- `/webhook-documentation` documents the current webhook recipient setup, supported authentication modes, and the details that should stay aligned with future webhook delivery changes.

## Main Routes

- `/` redirects authenticated users to the dashboard and guests to the login page.
- `/dashboard` is the main post-login landing page and shows the current system totals.
- `/recipients` is the admin recipient and group management page.
- `/services` is the admin service and service group management page.
- `/users` is the admin user management page.
- `/api-keys` is the admin API key management page.
- `/settings/profile`, `/settings/appearance`, and `/settings/security` manage account preferences.
- `/user-guide`, `/api-documentation`, and `/webhook-documentation` provide internal documentation pages.

## API Key Permissions

Available API key permissions are defined in `config/api_keys.php`.

- Add each new app section to the `resources` array.
- Every listed resource automatically receives `read` and `write` permissions.
- When new sections or API capabilities ship, update this config, the relevant tests, and the documentation in the same change.

## Local Development

This repository includes `.ddev` and should be worked on through DDEV whenever Docker is available.

### Common commands

```bash
ddev start
ddev composer install --no-interaction --prefer-dist
ddev exec php artisan migrate --seed
ddev exec php artisan test --compact
ddev npm run dev
```

If DDEV is unavailable, host-side Artisan and test commands may still work, but DDEV remains the preferred workflow for this project.

### Seeded access

Running the database seeder provisions two verified accounts for local development:

- `admin@example.com` / `password`
- `user@example.com` / `password`

The seeder also creates sample recipient groups, recipients, and API keys so the dashboard and management screens have representative data immediately.
It also seeds service groups and services so the new routing views have meaningful examples on a fresh install.

## Testing

Run focused tests while developing:

```bash
ddev exec php artisan test --compact tests/Feature/DocumentationPagesTest.php
```

Run the full test suite when needed:

```bash
ddev exec php artisan test --compact
```

## Documentation Maintenance

The following files are part of the project’s living documentation and should be updated whenever related behavior changes:

- `README.md` for setup, architecture, feature summaries, routes, and developer workflow
- `resources/views/pages/⚡user-guide.blade.php` for user-facing workflow instructions
- `resources/views/pages/⚡api-documentation.blade.php` for API capabilities, contracts, authentication, and examples
- `resources/views/pages/⚡webhook-documentation.blade.php` for webhook recipient setup, authentication, payload expectations, and delivery guidance
- `config/api_keys.php` for the API key permission registry used by the admin UI and future API authorization

If a feature, route, role, workflow, UI label, setup step, or API behavior changes, update the relevant documentation files in the same change.
If webhook configuration, authentication, payload shape, retry behavior, or delivery semantics change, update the webhook documentation page in the same change.
If a new management area or top-level app feature is added, update the dashboard stats as part of the same change when it should be surfaced there.
