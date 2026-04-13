# Is It Down

## Overview

Is It Down is a Laravel 13 and Livewire 4 application for managing monitored services and the recipients who should be notified about them. The project currently focuses on authenticated access, role-based administration, recipient management, service management, service templates, grouped routing targets, scheduled uptime checks, webhook and email delivery, user/account management, personal API keys, and a versioned REST API for integrations.

## Current Features

### Authenticated application shell

- Users sign in through Laravel Fortify.
- Verified users are routed to the dashboard.
- The dashboard shows a live service-status grid plus high-level totals for recipients, recipient groups, services, downtime incidents, templates, service groups, users, and API keys.
- Admins can open those dashboard stats to jump straight into the matching management screens.
- Authenticated users can access profile, appearance, and security settings.
- When Dave is enabled and configured by an admin, authenticated users also get a floating bottom-right chat launcher across the application shell.

### Security auditing

- Laravel Warden is installed for dependency and configuration audits.
- Local high-severity audits can be run through Composer scripts.
- GitHub Actions runs a Warden audit before PHPUnit in the main test workflow.

### Role-based access

- `admin` users can manage recipients, recipient groups, services, templates, service groups, users, and API keys.
- `user` accounts can access the authenticated application and their own settings.
- The system prevents the last remaining admin from being downgraded.

### Recipient management

- Create, edit, and delete recipients.
- Search the Recipients page to quickly filter recipient rows.
- Choose `Email` or `Webhook` in the UI while the application stores `mailto://` and `webhook://` endpoints internally.
- Add zero or more extra webhook headers per recipient when downstream systems need custom metadata on every webhook delivery.
- Configure webhook authentication as:
  - none
  - bearer token
  - basic auth
  - custom header
- Assign recipients to one or more groups.
- Open the Recipient Groups page when you want to manage group membership from the group side.
- Confirm recipient deletion in a modal before it is removed.

### Recipient group management

- Create, rename, and delete recipient groups.
- Search the Recipient Groups page by group name or linked recipient.
- Add or remove recipients from each group directly on the Recipient Groups page.
- Confirm group deletion before it is applied.

### Service management

- Create, edit, and delete services.
- Search the Services page to filter service cards from one input.
- Configure a service name, URL, polling interval, optional extra request headers, and an optional expectation using either plain text or a regular expression.
- Enable SSL expiry notifications per service to warn recipients when an HTTPS certificate is within 10 days of expiry, with those alerts limited to once every 24 hours per service.
- Save any existing service as a reusable template without its URL.
- Assign services to one or more service groups.
- Assign recipients and recipient groups directly to a service.
- Open the Service Groups page when you want to manage service-group membership and routing from the group side.
- Track the latest monitoring status, how long the service has been in that state, the last reason, the last check time, and a live next-check timer from the Services page.
- Mark a service as down when the response is not HTTP 200 or when a configured text or regex expectation does not match the response body.
- Retry one failed check after a short pause before classifying the service as down, which helps reduce false positives caused by brief network or origin blips.
- Notify assigned recipients only when the service changes state, so repeated down checks do not resend the same alert until the service recovers.
- Record downtime incidents with start and recovery reasons, response codes, retry attempt counts, screenshots, and optional Dave-generated outage analysis.
- Show 30-day uptime percentages plus recent downtime history directly on the Services page and the dashboard.
- Deliver email alerts to `mailto://` recipients and JSON payloads to `webhook://` recipients using the authentication method saved on each webhook recipient.
- Include structured downtime context in status-change notifications so emails and webhook consumers can see outage duration, screenshots, and AI analysis when available.
- Email all admin users if any webhook delivery fails during a status-change notification.
- Review the effective recipients for a service, including whether each route is direct, comes from a recipient group, or is inherited through a service group.

### Template management

- Create, edit, and delete reusable service templates.
- Store the same service settings you normally configure on the Services page except for the URL, including extra request headers and the SSL expiry notification default.
- Save templates directly from the Templates page or create them from an existing service with a name prompt.
- Start a new service from a template, which pre-fills the service form so only the URL and any final adjustments are needed.
- Search the Templates page by template name, saved service defaults, or routing assignments.

### Service group management

- Create, rename, and delete service groups.
- Link services to a service group from the Service Groups page.
- Assign direct recipients and recipient groups to a service group.
- Reuse those service groups across multiple monitored services.
- Search the Service Groups page by group name, linked service, recipient group, or recipient.
- Confirm service group deletion before it is applied.

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
- Keys support per-section `read` and `write` permissions, including the `templates` area.
- API keys are stored securely as hashes and the plain-text token is only shown once in a post-create modal.
- Keys can be revoked without removing the database record.

### Dave

- Admins can configure Dave from `/settings/ai-assistant`.
- The Dave settings link appears in the admin settings navigation.
- Dave stays hidden until an admin enables it and saves a provider URL, model, and API key.
- Dave appears as a floating chat launcher in the bottom-right corner of authenticated pages.
- Conversations stay open across closes and page navigation for the current browser session.
- Standard users can ask Dave for help with monitoring and outage questions, inspect downtime history, run a live website check, and send a test email to themselves.
- Admins can also ask Dave to create, update, and delete users, recipients, and services, and can send mail-setting test messages to another target email address when needed.
- When Dave is enabled and a website-style check fails after connecting successfully, the monitoring flow can ask Dave for a short explanation of what the failure might mean and include that in notifications and downtime records.
- The assistant rules and management tool guidance are centralized in `app/Support/AiAssistant/AiAssistantRules.php` and `app/Support/AiAssistant/AiAssistantToolExecutor.php`, which should be updated when new features or management flows are added.

### REST API

- Versioned API routes live under `/api/v1`.
- Requests authenticate with a bearer token using a stored API key hash.
- Expired, revoked, or unlinked API keys are rejected automatically.
- `recipients:*` permissions cover recipients and recipient groups.
- `services:*` permissions cover services and service groups.
- `history:*` permissions cover downtime history endpoints.
- `templates:*` permissions cover service templates.
- `users:*` permissions cover user listing and management.
- Listing endpoints support search plus resource-specific filtering where relevant.
- Creation endpoints reuse the same validation rules as the matching Livewire management forms.
- Services can be created from a saved service template by passing a template id or exact template name together with the URL and any optional overrides.
- Service and template payloads support `additional_headers` plus `ssl_expiry_notifications_enabled` so integrations can manage custom check headers and SSL warning behavior.
- Recipient payloads support `additional_headers` so webhook consumers can receive custom headers without hard-coding them elsewhere.
- Service responses now expose uptime history metadata, current downtime details, and recent downtime incidents.
- Downtime history endpoints expose screenshots, retry counts, and Dave outage summaries for integrations.

### In-app documentation

- `/user-guide` contains user-facing guidance for the features currently available.
- `/api-documentation` documents the current REST API, its authentication requirements, permissions, and endpoint contracts.
- `/api-playground` provides an authenticated in-app playground for trying documented API endpoints with a supplied API key.
- `/webhook-documentation` documents the current webhook recipient setup, supported authentication modes, and the details that should stay aligned with future webhook delivery changes.

## Main Routes

- `/` redirects authenticated users to the dashboard and guests to the login page.
- `/dashboard` is the main post-login landing page and shows the current system totals.
- `/recipients` is the admin recipient management page.
- `/recipient-groups` is the admin recipient group management page.
- `/services` is the admin service management page.
- `/service-templates` is the admin service template management page.
- `/service-groups` is the admin service group management page.
- `/users` is the admin user management page.
- `/api-keys` is the admin API key management page.
- `/settings/ai-assistant` is the admin Dave configuration page.
- `/api/v1/*` is the authenticated REST API surface for recipients, recipient groups, services, service templates, service groups, and users.
- `/settings/profile`, `/settings/appearance`, and `/settings/security` manage account preferences.
- `/user-guide`, `/api-documentation`, `/api-playground`, and `/webhook-documentation` provide internal documentation pages.

## API Key Permissions

Available API key permissions are defined in `config/api_keys.php`.

- Add each new app section to the `resources` array.
- Every listed resource automatically receives `read` and `write` permissions.
- When new sections or API capabilities ship, update this config, the relevant tests, the API routes, the endpoint catalog, and the documentation in the same change.

## Docker Deployment

The repository now publishes a container image to GitHub Container Registry as `ghcr.io/leeappzrun-arch/is-it-down:latest` whenever the default branch is updated.

Release tags also publish versioned images. For example, pushing Git tag `v1.2.3` publishes:

- `ghcr.io/leeappzrun-arch/is-it-down:1.2.3`
- `ghcr.io/leeappzrun-arch/is-it-down:1.2`
- `ghcr.io/leeappzrun-arch/is-it-down:1`

The image already includes a production `.env` with non-sensitive defaults for the app name, production mode, SQLite, database-backed sessions/cache/queue, stderr logging, and the scheduler loop. Any values you pass from your own Compose `.env` file override those baked-in defaults.
The runtime image also declares `/var/www/html/database/data` and `/var/www/html/storage/app/public` as Docker volumes, so SQLite data, the generated `app.key`, and captured downtime screenshots survive ordinary container recreation during image updates. Using your own bind mounts or named volumes is still recommended so you control where that data lives.

If the app sits behind Cloudflare Tunnel, Zero Trust, or another reverse proxy that terminates HTTPS before the container, keep `APP_URL` set to the public `https://...` address. The application trusts standard forwarded proxy headers so Livewire update requests, generated URLs, and redirects continue to use HTTPS.

Create a `docker-compose.yml` file like this:

```yaml
services:
  is-it-down:
    image: ghcr.io/leeappzrun-arch/is-it-down:latest
    pull_policy: always
    restart: unless-stopped
    env_file:
      - ./.env
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - "${APP_DATA_DIR:-./data}:/var/www/html/database/data"
      - "${APP_STORAGE_DIR:-./storage}:/var/www/html/storage/app/public"
```

Create a matching `.env` file beside it:

```dotenv
APP_PORT=8080
APP_DATA_DIR=./data
APP_STORAGE_DIR=./storage
APP_URL=http://localhost:8080

MAIL_MAILER="smtp"
MAIL_HOST="127.0.0.1"
MAIL_PORT="1025"
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

INITIAL_ADMIN_NAME=Admin User
INITIAL_ADMIN_EMAIL=admin@example.com
INITIAL_ADMIN_PASSWORD=change-this-password

MONITORING_FAILURE_RETRY_DELAY_SECONDS=3
MONITORING_DOWNTIME_SCREENSHOT_DISK=public
MONITORING_DOWNTIME_SCREENSHOT_DIRECTORY=downtime-screenshots
```

Then start the application with:

```bash
docker compose up -d
```

On the first boot the container will:

- create `database.sqlite` inside your mapped data directory
- generate and persist an `APP_KEY` in `app.key` inside that same data directory when you have not supplied one yourself
- run the Laravel migrations
- create the initial admin account from `INITIAL_ADMIN_*` when you provide those values
- create the public storage symlink used for captured downtime screenshots
- start the Laravel scheduler loop so monitoring continues to run inside the same container

To change the public port, update `APP_PORT` in your `.env` file.

To move the persistent data somewhere else on the host, update `APP_DATA_DIR`.
To store captured downtime screenshots somewhere else on the host, update `APP_STORAGE_DIR`.
To stay on a fixed release instead of tracking new builds, change the image tag in `docker-compose.yml`, for example:

```yaml
image: ghcr.io/leeappzrun-arch/is-it-down:1.2.3
```

To publish a new pinned release image, create and push a semantic version tag:

```bash
git tag v1.2.3
git push origin v1.2.3
```

To upgrade to the newest published build, run:

```bash
docker compose pull
docker compose up -d
```

That update flow recreates the container and then runs `app:prepare-container` against the existing persisted SQLite database, so Laravel only applies any new migrations that have not run yet. Your existing data and stored downtime screenshots remain in place unless you deliberately remove the mapped or anonymous volumes, such as with `docker compose down -v`.

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
It also seeds service groups, services, service templates, recipient webhook headers, and example downtime history so the routing views, uptime cards, and outage history features have meaningful examples on a fresh install.

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
