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

    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('On this page') }}</flux:heading>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="#current-scope" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Current scope') }}</a>
            <a href="#destination-format" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Destination format') }}</a>
            <a href="#authentication-options" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Authentication options') }}</a>
            <a href="#additional-headers" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Additional headers') }}</a>
            <a href="#payload-shape" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Payload shape') }}</a>
            <a href="#security-and-storage" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Security and storage') }}</a>
            <a href="#grouping-and-administration" class="rounded-lg border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:text-zinc-100">{{ __('Grouping and administration') }}</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            <div id="current-scope" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Current scope') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients are created and maintained from the Recipients page by admin users.') }}</p>
                    <p>{{ __('Webhook alerts are delivered when a monitored service changes state, such as going down or coming back up.') }}</p>
                    <p>{{ __('Webhook recipients can also receive SSL expiry warnings when a service has that option enabled and its HTTPS certificate is within 10 days of expiry.') }}</p>
                    <p>{{ __('This page documents how webhook destinations are configured, validated, stored, secured, and what payload shape is currently sent by the monitoring scheduler.') }}</p>
                </div>
            </div>

            <div id="destination-format" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Destination format') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Choose `Webhook` as the protocol on the Recipients page, then enter the destination without the internal `webhook://` storage prefix.') }}</p>
                    <p>{{ __('The form accepts either a full URL such as `https://hooks.example.com/services/status` or a hostname and path such as `hooks.example.com/services/status`.') }}</p>
                    <p>{{ __('Webhook endpoints are stored internally as `webhook://...` values so they can be distinguished from `mailto://...` email recipients.') }}</p>
                    <p>{{ __('Validation rejects blank or malformed webhook targets before the recipient can be saved.') }}</p>
                </div>
            </div>

            <div id="authentication-options" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Authentication options') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients support four authentication modes: none, bearer token, basic auth, and custom header.') }}</p>
                    <p>{{ __('The authentication fields appear immediately after `Webhook` is selected and update again as soon as the authentication type changes.') }}</p>
                    <p>{{ __('Bearer token mode requires a single token value. Basic auth requires both a username and password. Custom header mode requires both a header name and a header value.') }}</p>
                    <p>{{ __('The recipient table shows a human-readable summary such as `No authentication`, `Bearer token`, `Basic auth`, or `Custom header` so admins can review each destination safely.') }}</p>
                </div>
            </div>

            <div id="additional-headers" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Additional headers') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients can define zero or more additional headers alongside the main authentication mode.') }}</p>
                    <p>{{ __('These headers are added to every webhook request before the built-in authentication helper is applied, which makes them useful for environment tags, routing keys, tenant identifiers, or secondary auth systems.') }}</p>
                    <p>{{ __('Each additional header requires both a name and a value, and blank header names are ignored when requests are built.') }}</p>
                </div>
            </div>

            <div id="payload-shape" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payload shape') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook payloads are sent as JSON and include the event name plus the relevant service details for that alert type.') }}</p>
                    <p>{{ __('Status-change events include the new status, the previous status, the check timestamp, the attempt count, the HTTP response code when available, and the reason the service was considered up or down.') }}</p>
                    <p>{{ __('When the failed check returned headers, the payload also includes `response_headers`. When an outage record exists, the payload includes a `downtime` object with timestamps, reasons, a screenshot URL when one was stored, failed response headers, Dave analysis when available, and a duration block once the outage has been resolved.') }}</p>
                    <p>{{ __('SSL-expiry events use the `service.ssl_expiring` event name and include an `ssl` object with the certificate expiry timestamp, signed days remaining, and a human-readable summary.') }}</p>
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
  "checked_at": "2026-04-04T10:30:00+00:00",
  "attempt_count": 2,
  "response_code": 200,
  "reason": "Received an HTTP 200 response and the expected text was present.",
  "downtime": {
    "id": 17,
    "started_at": "2026-04-04T10:25:00+00:00",
    "ended_at": "2026-04-04T10:30:00+00:00",
    "started_reason": "Expected HTTP 200 response but received 503.",
    "latest_reason": "Expected HTTP 200 response but received 503.",
    "recovery_reason": "Received an HTTP 200 response and the expected text was present.",
    "screenshot_url": "https://status.example.com/storage/downtime-screenshots/marketing-site.png",
    "latest_response_headers": [
      {
        "name": "Content-Type",
        "value": "text/html; charset=UTF-8"
      }
    ],
    "ai_summary": "The upstream likely served a maintenance page or temporary origin error.",
    "duration": {
      "seconds": 300,
      "human": "5 minutes"
    }
  }
}</code></pre>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div id="security-and-storage" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Security and storage') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Sensitive webhook credential values are stored separately from the endpoint so authentication can be changed without rewriting the destination address.') }}</p>
                    <p>{{ __('Webhook passwords, bearer tokens, and custom header values are encrypted at rest by the model casts used by the application.') }}</p>
                    <p>{{ __('Stored downtime screenshots are referenced by URL in webhook payloads when available, so recipients can review the captured page state without the image bytes being embedded directly in the JSON body.') }}</p>
                    <p>{{ __('Old resolved downtime screenshots are pruned automatically alongside downtime records once they age out of the retention window.') }}</p>
                    <p>{{ __('Changing a recipient from `Webhook` back to `Email` clears webhook-specific authentication fields from the form.') }}</p>
                </div>
            </div>

            <div id="grouping-and-administration" class="scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Grouping and administration') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Webhook recipients can be assigned to one or more recipient groups during creation or editing from the Recipients page, or by managing membership from the Recipient Groups page.') }}</p>
                    <p>{{ __('Admins can edit an existing webhook recipient from the Recipients management table, which scrolls the form into view and reloads the saved authentication state.') }}</p>
                    <p>{{ __('Deleting a webhook recipient uses the same confirmation modal as other management actions so removal is never immediate from the table row.') }}</p>
                    <p>{{ __('If any webhook delivery fails during a status-change alert or an SSL-expiry warning, every admin user receives an email describing which webhook failed and why.') }}</p>
                </div>
            </div>

        </div>
    </div>
</section>
