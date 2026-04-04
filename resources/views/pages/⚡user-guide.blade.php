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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Getting started') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Sign in with your account to access the application. Verified users are taken to the dashboard after login.') }}</p>
                    <p>{{ __('If you are a standard user, your day-to-day actions are currently centered around your account settings and reviewing the in-app guidance pages.') }}</p>
                    <p>{{ __('If you are an admin, the main navigation will show Monitoring for Dashboard, Recipients, and Services, plus Access for Users and API Keys.') }}</p>
                    <p>{{ __('If an admin has configured the AI assistant, a floating chat launcher will appear in the bottom-right corner of the app so you can ask for help without leaving the page.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Dashboard') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The dashboard is the default landing page after authentication and shows a live service-status grid above the headline totals for recipients, recipient groups, services, service groups, users, and API keys.') }}</p>
                    <p>{{ __('Admins can select those dashboard cards to move directly into the matching management page. Standard users can review the totals but cannot click through to admin-only tools.') }}</p>
                    <p>{{ __('Use the sidebar to move into administration screens, account settings, and supporting documentation.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Recipient management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Recipients are delivery targets for notifications or outbound events. Each recipient must have a name and an endpoint.') }}</p>
                    <p>{{ __('Choose `Email` or `Webhook` from the protocol selector, then enter the address or target without the internal prefix.') }}</p>
                    <p>{{ __('Email destinations are stored internally as `mailto://name@example.com`, while webhooks are stored as `webhook://example.com/path` or `webhook://https://example.com/path`.') }}</p>
                    <p>{{ __('Webhook recipients can be configured with no authentication, bearer token authentication, basic authentication, or a custom header, and the matching fields appear as soon as you choose the authentication type.') }}</p>
                    <p>{{ __('Use the Webhook Documentation page for a dedicated reference on supported destination formats, authentication options, and the details that should stay aligned with future webhook delivery work.') }}</p>
                    <p>{{ __('Recipients can belong to multiple groups, which makes it easier to organise related delivery targets together.') }}</p>
                    <p>{{ __('Use the sticky search field at the top of the Recipients page to filter both the recipient table and the existing groups list without changing the form selections on the page.') }}</p>
                    <p>{{ __('Existing recipients can be edited or deleted from the management table once they have been created. Editing scrolls you to the form automatically, and deletes ask for confirmation before anything is removed.') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Group management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Groups let you organise recipients into reusable collections such as Operations, Finance, or On-call.') }}</p>
                    <p>{{ __('Create groups from the side panel on the Recipients page, then assign one or more groups while creating or editing a recipient.') }}</p>
                    <p>{{ __('Groups can be renamed or deleted later as your routing structure evolves. Editing scrolls you back to the group form, and group deletion also uses a confirmation modal.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Service management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Services represent the URLs you want to monitor. Each service includes a name, URL, polling interval, and an optional expectation that can be plain text or a regex pattern.') }}</p>
                    <p>{{ __('A service is considered down whenever the URL does not respond with HTTP 200, or when a configured text or regex expectation fails to match the response body.') }}</p>
                    <p>{{ __('A service can have direct recipients, direct recipient groups, and one or more service groups attached at the same time.') }}</p>
                    <p>{{ __('Use the sticky search field at the top of the Services page to narrow both managed services and service groups from a single query.') }}</p>
                    <p>{{ __('Each service opens as an accordion so the list stays compact while still exposing the current monitoring state, how long the service has been in that state, the latest reason, the last check time, the next check timer, and the full effective recipient breakdown when you expand a service.') }}</p>
                    <p>{{ __('Recipients are only notified when a service changes state. A service that stays down will not keep sending repeated down alerts on every interval, but a recovery alert is sent once it comes back up and includes how long the outage lasted.') }}</p>
                    <p>{{ __('Editing a service scrolls the form back into view, and deleting a service asks for confirmation before it is removed.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Service group management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Service groups are reusable routing bundles. They can contain both direct recipients and recipient groups, then be attached to multiple services.') }}</p>
                    <p>{{ __('Use service groups when several monitored services should share the same escalation or stakeholder routing setup.') }}</p>
                    <p>{{ __('Like the other management screens, editing scrolls you to the form and deletion is protected behind a confirmation modal.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
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

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('API key management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Admins can create API keys from the API Keys page, and every key is automatically linked to the signed-in account that created it.') }}</p>
                    <p>{{ __('The sticky page-level search field filters issued keys by key details, ownership information, permissions, and status so older keys are easier to find.') }}</p>
                    <p>{{ __('Every key can be given read and write access for each supported application area, including Services, Users, and Recipients.') }}</p>
                    <p>{{ __('Expiration can be set to 6 months, 1 year, 2 years, or never. The plain-text key is only shown once in a confirmation modal when it is created, so it should be copied immediately.') }}</p>
                    <p>{{ __('Keys can be revoked later without deleting the audit trail of who created them and what access they were given.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('AI assistant') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The AI assistant appears as a floating button in the bottom-right corner of the application, but only after an admin has enabled it and added valid provider settings.') }}</p>
                    <p>{{ __('Admins can reach the AI Assistant configuration from the Settings area.') }}</p>
                    <p>{{ __('If you close the chat and open it again later, or move to another page, the current conversation should stay available during the same browser session.') }}</p>
                    <p>{{ __('Standard users can use it for guidance about outages, monitoring state, and the way the current system works.') }}</p>
                    <p>{{ __('Admins can additionally use it to create, edit, and delete users, recipients, and services when the request is clear enough to act on safely.') }}</p>
                    <p>{{ __('If the assistant cannot act because something is ambiguous or your account lacks permission, it should explain that clearly instead of pretending the change happened.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('API access') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The application now exposes a REST API under `/api/v1`, authenticated with bearer tokens generated from the API Keys page.') }}</p>
                    <p>{{ __('Read routes and write routes each require the matching API key permissions, and expired or revoked keys stop working immediately.') }}</p>
                    <p>{{ __('Use the API Documentation page for the full endpoint reference and the API Playground page to test those endpoints against the current environment.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Account settings') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('All authenticated users can manage their profile details from Settings.') }}</p>
                    <p>{{ __('Appearance settings are available for adjusting the way the app is presented.') }}</p>
                    <p>{{ __('Security settings include password confirmation flows and support for two-factor authentication when enabled.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-950/40">
                <flux:heading size="lg">{{ __('Need a quick reference?') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Use this page for user-facing workflow guidance, the API Documentation page for endpoint contracts, the API Playground page for live testing, the Webhook Documentation page for recipient delivery and payload notes, and the project README for setup and technical reference.') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
