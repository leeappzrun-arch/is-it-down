<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API documentation')] class extends Component {
    //
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-3xl rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="xl" level="1">{{ __('API Documentation') }}</flux:heading>
        <flux:subheading size="lg" class="mt-3">{{ __('API key preparation') }}</flux:subheading>

        <div class="mt-6 space-y-4 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
            <p>{{ __('Public or internal API endpoints have not been introduced yet, so there are no live base URLs, request schemas, or response examples to document today.') }}</p>
            <p>{{ __('Admins can already create API keys ahead of that work from the API Keys page. Keys can belong to the current admin account or to a named service integration, they support per-section read and write permissions for areas such as Recipients, Services, and Users, plus optional expiration dates, and the plain-text key is only revealed once in a confirmation modal.') }}</p>
            <p>{{ __('Webhook recipient configuration is documented separately on the Webhook Documentation page so it can evolve independently from the future HTTP API.') }}</p>
            <p>{{ __('When the API is added, this page should be updated with authentication headers, endpoint contracts, example payloads, and the permission requirements for each route.') }}</p>
        </div>
    </div>
</section>
