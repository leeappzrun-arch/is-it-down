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
            {{ __('A practical walkthrough of the features currently available in Is It Down.') }}
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
                    <p>{{ __('If you are an admin, the main navigation will show Monitoring for Dashboard and Recipients, plus Access for Users and API Keys.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Dashboard') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('The dashboard is the default landing page after authentication and shows headline totals for recipients, recipient groups, users, and API keys.') }}</p>
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
                <flux:heading size="lg">{{ __('User management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Admins can create new users, assign roles, update existing user roles, and delete standard users from the Users page.') }}</p>
                    <p>{{ __('Two roles currently exist: `admin` and `user`. Admins can access management screens, while standard users have a more limited experience.') }}</p>
                    <p>{{ __('The system protects the final admin account from being downgraded, which prevents accidental lockout from admin-only tools.') }}</p>
                    <p>{{ __('Deleting a standard user requires confirmation, and admin accounts are intentionally protected from deletion on that screen.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('API key management') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Admin only') }}</flux:subheading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Admins can create API keys for their own account or for a named service from the API Keys page.') }}</p>
                    <p>{{ __('Every key can be given read and write access for each supported application area, including Users and Recipients.') }}</p>
                    <p>{{ __('Expiration can be set to 6 months, 1 year, 2 years, or never. The plain-text key is only shown once in a confirmation modal when it is created, so it should be copied immediately.') }}</p>
                    <p>{{ __('Keys can be revoked later without deleting the audit trail of who created them and what access they were given.') }}</p>
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
                    <p>{{ __('Use this page for user-facing workflow guidance, the API Documentation page for current API status, the Webhook Documentation page for recipient delivery setup notes, and the project README for setup and technical reference.') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
