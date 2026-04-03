# Is It Down

## Overview

Is It Down is a Laravel 13 and Livewire 4 application for managing who receives notifications or delivery events. The project currently focuses on authenticated access, role-based administration, recipient management, grouped routing targets, and user/account management.

## Current Features

### Authenticated application shell

- Users sign in through Laravel Fortify.
- Verified users are routed to the dashboard.
- Authenticated users can access profile, appearance, and security settings.

### Role-based access

- `admin` users can manage recipients and users.
- `user` accounts can access the authenticated application and their own settings.
- The system prevents the last remaining admin from being downgraded.

### Recipient management

- Create, edit, and delete recipients.
- Support `mailto://` endpoints for email destinations.
- Support `webhook://` endpoints for webhook destinations.
- Configure webhook authentication as:
  - none
  - bearer token
  - basic auth
  - custom header
- Assign recipients to one or more groups.

### Group management

- Create, rename, and delete recipient groups.
- Use groups to organise related recipients for future routing use cases.

### User management

- Admins can create users.
- Admins can assign and update roles.

### In-app documentation

- `/user-guide` contains user-facing guidance for the features currently available.
- `/api-documentation` is reserved for API documentation and currently shows a placeholder until API work begins.

## Main Routes

- `/` redirects authenticated users to the dashboard and guests to the login page.
- `/dashboard` is the main post-login landing page.
- `/recipients` is the admin recipient and group management page.
- `/users` is the admin user management page.
- `/settings/profile`, `/settings/appearance`, and `/settings/security` manage account preferences.
- `/user-guide` and `/api-documentation` provide internal documentation pages.

## Local Development

This repository includes `.ddev` and should be worked on through DDEV whenever Docker is available.

### Common commands

```bash
ddev start
ddev composer install --no-interaction --prefer-dist
ddev exec php artisan migrate
ddev exec php artisan test --compact
ddev npm run dev
```

If DDEV is unavailable, host-side Artisan and test commands may still work, but DDEV remains the preferred workflow for this project.

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

If a feature, route, role, workflow, UI label, setup step, or API behavior changes, update the relevant documentation files in the same change.
