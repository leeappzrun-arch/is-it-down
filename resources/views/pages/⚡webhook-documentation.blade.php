<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Webhook documentation')] class extends Component {
    //
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Webhook Documentation') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('Reference notes for configuring webhook recipients in the current version of Is It Down.') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Current scope') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients are created and maintained from the Recipients page by admin users.') }}</p>
                    <p>{{ __('Webhook alerts are delivered when a monitored service changes state, such as going down or coming back up.') }}</p>
                    <p>{{ __('This page documents how webhook destinations are configured, validated, stored, secured, and what payload shape is currently sent by the monitoring scheduler.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Destination format') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Choose `Webhook` as the protocol on the Recipients page, then enter the destination without the internal `webhook://` storage prefix.') }}</p>
                    <p>{{ __('The form accepts either a full URL such as `https://hooks.example.com/services/status` or a hostname and path such as `hooks.example.com/services/status`.') }}</p>
                    <p>{{ __('Webhook endpoints are stored internally as `webhook://...` values so they can be distinguished from `mailto://...` email recipients.') }}</p>
                    <p>{{ __('Validation rejects blank or malformed webhook targets before the recipient can be saved.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Authentication options') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients support four authentication modes: none, bearer token, basic auth, and custom header.') }}</p>
                    <p>{{ __('The authentication fields appear immediately after `Webhook` is selected and update again as soon as the authentication type changes.') }}</p>
                    <p>{{ __('Bearer token mode requires a single token value. Basic auth requires both a username and password. Custom header mode requires both a header name and a header value.') }}</p>
                    <p>{{ __('The recipient table shows a human-readable summary such as `No authentication`, `Bearer token`, `Basic auth`, or `Custom header` so admins can review each destination safely.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payload shape') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook payloads are sent as JSON and include the event name, service details, the new status, the previous status, the check timestamp, the HTTP response code when available, and the reason the service was considered up or down.') }}</p>
                    <p>{{ __('When a service recovers, the payload also includes a `downtime_duration` object so downstream systems can see how long the outage lasted.') }}</p>
                    <p>{{ __('The `service.expectation` object is included when the service uses a text or regex expectation, which helps downstream systems understand why a response body was treated as healthy or unhealthy.') }}</p>
                    <p>{{ __('A representative payload looks like this:') }}</p>
                    <pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs leading-6 text-zinc-100"><code>{
  "event": "service.status_changed",
  "service": {
    "id": 1,
    "name": "Marketing Site",
    "url": "https://example.com/status",
    "interval_seconds": 60,
    "expectation": {
      "type": "text",
      "value": "All systems operational"
    }
  },
  "status": "up",
  "previous_status": "down",
  "downtime_duration": {
    "seconds": 300,
    "human": "5 minutes"
  },
  "checked_at": "2026-04-04T10:30:00+00:00",
  "response_code": 200,
  "reason": "Received an HTTP 200 response and the expected text was present."
}</code></pre>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Security and storage') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Sensitive webhook credential values are stored separately from the endpoint so authentication can be changed without rewriting the destination address.') }}</p>
                    <p>{{ __('Webhook passwords, bearer tokens, and custom header values are encrypted at rest by the model casts used by the application.') }}</p>
                    <p>{{ __('Changing a recipient from `Webhook` back to `Email` clears webhook-specific authentication fields from the form.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Grouping and administration') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients can be assigned to one or more recipient groups during creation or editing.') }}</p>
                    <p>{{ __('Admins can edit an existing webhook recipient from the management table, which scrolls the form into view and reloads the saved authentication state.') }}</p>
                    <p>{{ __('Deleting a webhook recipient uses the same confirmation modal as other management actions so removal is never immediate from the table row.') }}</p>
                    <p>{{ __('If any webhook delivery fails during a status-change alert, every admin user receives an email describing which webhook failed and why.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-950/40">
                <flux:heading size="lg">{{ __('Keep this page current') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Whenever webhook validation rules, supported authentication modes, payload structure, headers, delivery timing, retry behavior, permissions, or navigation change, update this page in the same pull request.') }}</p>
                    <p>{{ __('Use the User Guide for broader workflow help and the API Documentation page for future HTTP API contracts that are separate from recipient configuration.') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
