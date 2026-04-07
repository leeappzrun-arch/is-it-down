<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User guide')] class extends Component {
    //
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('User Guide') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('A practical walkthrough of the features currently available in Is It Down?') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('On this page') }}</flux:heading>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <a href="#getting-started" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Getting started') }}</a>
            <a href="#dashboard" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Dashboard') }}</a>
            <a href="#recipient-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Recipient management') }}</a>
            <a href="#recipient-group-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Recipient group management') }}</a>
            <a href="#service-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Service management') }}</a>
            <a href="#template-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Template management') }}</a>
            <a href="#service-group-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Service group management') }}</a>
            <a href="#user-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('User management') }}</a>
            <a href="#api-key-management" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('API key management') }}</a>
            <a href="#dave" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Dave') }}</a>
            <a href="#api-access" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('API access') }}</a>
            <a href="#account-settings" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Account settings') }}</a>
            <a href="#quick-reference" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Need a quick reference?') }}</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            <div id="getting-started" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Getting started') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Sign in with your account to access the application. Verified users are taken to the dashboard after login.') }}</p>
                    <p>{{ __('If you are a standard user, your day-to-day actions are currently centered around your account settings and reviewing the in-app guidance pages.') }}</p>
                    <p>{{ __('If you are an admin, the main navigation will show Monitoring for Dashboard, Recipients, Recipient groups, Services, Service Templates, and Service groups, plus Access for Users and API Keys.') }}</p>
                    <p>{{ __('If an admin has configured Dave, a floating chat launcher will appear in the bottom-right corner of the app so you can ask for help without leaving the page.') }}</p>
                </div>
            </div>

            <div id="dashboard" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Dashboard') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The dashboard is the default landing page after authentication and shows a live service-status grid above the headline totals for recipients, recipient groups, services, templates, service groups, users, and API keys.') }}</p>
                    <p>{{ __('Admins can select those dashboard cards to move directly into the matching management page for recipients, recipient groups, services, templates, service groups, users, or API keys. Standard users can review the totals but cannot click through to admin-only tools.') }}</p>
                    <p>{{ __('Use the sidebar to move into administration screens, account settings, and supporting documentation.') }}</p>
                </div>
            </div>

            <div id="recipient-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Recipient management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Recipients are delivery targets for notifications or outbound events. Each recipient must have a name and an endpoint.') }}</p>
                    <p>{{ __('Choose `Email` or `Webhook` from the protocol selector, then enter the address or target without the internal prefix.') }}</p>
                    <p>{{ __('Email destinations are stored internally as `mailto://name@example.com`, while webhooks are stored as `webhook://example.com/path` or `webhook://https://example.com/path`.') }}</p>
                    <p>{{ __('Webhook recipients can be configured with no authentication, bearer token authentication, basic authentication, or a custom header, and the matching fields appear as soon as you choose the authentication type.') }}</p>
                    <p>{{ __('Use the Webhook Documentation page for a dedicated reference on supported destination formats, authentication options, and the details that should stay aligned with future webhook delivery work.') }}</p>
                    <p>{{ __('Recipients can belong to multiple groups, which makes it easier to organise related delivery targets together.') }}</p>
                    <p>{{ __('Use the sticky search field at the top of the Recipients page to filter the recipient table without changing the form selections on the page.') }}</p>
                    <p>{{ __('When you need to manage group membership from the other direction, open the dedicated Recipient Groups page.') }}</p>
                    <p>{{ __('Existing recipients can be edited or deleted from the management table once they have been created. Editing scrolls you to the form automatically, and deletes ask for confirmation before anything is removed.') }}</p>
                </div>
            </div>

            <div id="recipient-group-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Recipient group management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Groups let you organise recipients into reusable collections such as Operations, Finance, or On-call.') }}</p>
                    <p>{{ __('Use the Recipient Groups page to create groups, rename them, and add or remove members directly from the group side.') }}</p>
                    <p>{{ __('Groups can still be assigned while creating or editing a recipient on the Recipients page, so membership can be adjusted from either side.') }}</p>
                    <p>{{ __('Editing scrolls you back to the group form, and group deletion also uses a confirmation modal.') }}</p>
                </div>
            </div>

            <div id="service-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Service management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Services represent the URLs you want to monitor. Each service includes a name, URL, polling interval, and an optional expectation that can be plain text or a regex pattern.') }}</p>
                    <p>{{ __('A service is considered down whenever the URL does not respond with HTTP 200, or when a configured text or regex expectation fails to match the response body.') }}</p>
                    <p>{{ __('A service can have direct recipients, direct recipient groups, and one or more service groups attached at the same time.') }}</p>
                    <p>{{ __('Use the sticky search field at the top of the Services page to narrow managed services from a single query.') }}</p>
                    <p>{{ __('Use the Save as template action on any existing service when you want to capture its non-URL settings into a reusable starting point.') }}</p>
                    <p>{{ __('When you want to manage linked services or routing ingredients from the group side, open the dedicated Service Groups page.') }}</p>
                    <p>{{ __('Each service opens as an accordion so the list stays compact while still exposing the current monitoring state, how long the service has been in that state, the latest reason, the last check time, the next check timer, and the full effective recipient breakdown when you expand a service.') }}</p>
                    <p>{{ __('Recipients are only notified when a service changes state. A service that stays down will not keep sending repeated down alerts on every interval, but a recovery alert is sent once it comes back up and includes how long the outage lasted.') }}</p>
                    <p>{{ __('Editing a service scrolls the form back into view, and deleting a service asks for confirmation before it is removed.') }}</p>
                </div>
            </div>

            <div id="template-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Template management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Templates are reusable service blueprints. They store the same service settings you would normally configure on the Services page except for the URL, so you can reuse intervals, expectations, and routing assignments across new services.') }}</p>
                    <p>{{ __('Each template has its own template name plus a default service name that is copied into the service form when the template is used.') }}</p>
                    <p>{{ __('You can create templates directly from the Templates page or save one from an existing service by choosing Save as template from the service details.') }}</p>
                    <p>{{ __('When you choose Create service on a template, the app opens the Services page with the template values prefilled. Add the URL, review the fields, and save when you are ready.') }}</p>
                    <p>{{ __('Like the other admin pages, templates support sticky search, edit-to-form focus, and delete confirmation before anything is removed.') }}</p>
                </div>
            </div>

            <div id="service-group-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Service group management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Service groups are reusable routing bundles. They can contain both direct recipients and recipient groups, then be attached to multiple services.') }}</p>
                    <p>{{ __('Use service groups when several monitored services should share the same escalation or stakeholder routing setup.') }}</p>
                    <p>{{ __('The Service Groups page also lets you attach or remove services from a group directly, so service-to-group relationships can be managed from either side.') }}</p>
                    <p>{{ __('Like the other management screens, editing scrolls you to the form and deletion is protected behind a confirmation modal.') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div id="user-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('User management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Admins can create new users, assign roles, update existing user roles, and delete standard users from the Users page.') }}</p>
                    <p>{{ __('The sticky search field at the top of the Users page filters the management table by name, email address, or role.') }}</p>
                    <p>{{ __('Two roles currently exist: `admin` and `user`. Admins can access management screens, while standard users have a more limited experience.') }}</p>
                    <p>{{ __('The system protects the final admin account from being downgraded, which prevents accidental lockout from admin-only tools.') }}</p>
                    <p>{{ __('Deleting a standard user requires confirmation, and admin accounts are intentionally protected from deletion on that screen.') }}</p>
                </div>
            </div>

            <div id="api-key-management" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('API key management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Admins can create API keys from the API Keys page, and every key is automatically linked to the signed-in account that created it.') }}</p>
                    <p>{{ __('The sticky page-level search field filters issued keys by key details, ownership information, permissions, and status so older keys are easier to find.') }}</p>
                    <p>{{ __('Every key can be given read and write access for each supported application area, including Services, Service Templates, Users, and Recipients.') }}</p>
                    <p>{{ __('Expiration can be set to 6 months, 1 year, 2 years, or never. The plain-text key is only shown once in a confirmation modal when it is created, so it should be copied immediately.') }}</p>
                    <p>{{ __('Keys can be revoked later without deleting the audit trail of who created them and what access they were given.') }}</p>
                </div>
            </div>

            <div id="dave" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Dave') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Dave is the in-app AI assistant for Is It Down? He can answer questions about how the system works, provide guidance on monitoring and outage management best practices, and help admins with user, recipient, and service management tasks when the request is clear enough to act on safely.') }}</p>
                    <p>{{ __('Dave appears as a floating button in the bottom-right corner of the application, but only after an admin has enabled it and added valid provider settings.') }}</p>
                    <p>{{ __('Admins can reach the Dave configuration from the Settings area.') }}</p>
                    <p>{{ __('If you close the chat and open it again later, or move to another page, the current conversation should stay available during the same browser session.') }}</p>
                    <p>{{ __('Standard users can use Dave for guidance about outages, monitoring state, and the way the current system works.') }}</p>
                    <p>{{ __('Admins can additionally use Dave to create, edit, and delete users, recipients, and services when the request is clear enough to act on safely.') }}</p>
                    <p>{{ __('If Dave cannot act because something is ambiguous or your account lacks permission, it should explain that clearly instead of pretending the change happened.') }}</p>
                </div>
            </div>

            <div id="api-access" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('API access') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The application now exposes a REST API under `/api/v1`, authenticated with bearer tokens generated from the API Keys page.') }}</p>
                    <p>{{ __('Read routes and write routes each require the matching API key permissions, and expired or revoked keys stop working immediately. The current REST API now includes service-template endpoints and also allows new services to start from a saved template.') }}</p>
                    <p>{{ __('Use the API Documentation page for the full endpoint reference and the API Playground page to test those endpoints against the current environment.') }}</p>
                </div>
            </div>

            <div id="account-settings" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Account settings') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('All authenticated users can manage their profile details from Settings.') }}</p>
                    <p>{{ __('Appearance settings are available for adjusting the way the app is presented.') }}</p>
                    <p>{{ __('Security settings include password confirmation flows and support for two-factor authentication when enabled.') }}</p>
                </div>
            </div>

            <div id="quick-reference" class="scroll-mt-24 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-950/40">
                <flux:heading size="lg">{{ __('Need a quick reference?') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Use this page for user-facing workflow guidance, the API Documentation page for endpoint contracts, the API Playground page for live testing, the Webhook Documentation page for recipient delivery and payload notes, and the project README for setup and technical reference.') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
