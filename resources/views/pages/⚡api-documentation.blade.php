<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API documentation')] class extends Component {
    //
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-3xl rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="xl" level="1">{{ __('API Documentation') }}</flux:heading>
        <flux:subheading size="lg" class="mt-3">{{ __('Coming soon.') }}</flux:subheading>

        <div class="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
            <p>{{ __('This section will be expanded once public or internal API endpoints are introduced to the application.') }}</p>
        </div>
    </div>
</section>
