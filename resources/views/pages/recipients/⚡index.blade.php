<?php

use App\Concerns\RecipientValidation;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Support\Recipients\RecipientData;
use App\Support\Services\ServiceData;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recipient management')] class extends Component {
    use RecipientValidation;

    public string $search = '';

    public ?int $editingRecipientId = null;

    public string $name = '';

    public string $endpointType = Recipient::TYPE_MAIL;

    public string $endpointTarget = '';

    public string $webhookAuthType = Recipient::WEBHOOK_AUTH_NONE;

    public string $webhookAuthUsername = '';

    public string $webhookAuthPassword = '';

    public string $webhookAuthToken = '';

    public string $webhookAuthHeaderName = '';

    public string $webhookAuthHeaderValue = '';

    /** @var array<int, array{name: string, value: string}> */
    public array $additionalHeaders = [];

    /** @var array<int, string> */
    public array $selectedGroupIds = [];

    public bool $showDeleteConfirmationModal = false;

    public ?string $deleteConfirmationType = null;

    public ?int $deleteConfirmationId = null;

    public string $deleteConfirmationName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function recipients()
    {
        $recipients = Recipient::query()
            ->with('groups:id,name')
            ->orderBy('name')
            ->orderBy('endpoint')
            ->get();

        if ($this->searchTerm() === '') {
            return $recipients;
        }

        return $recipients
            ->filter(fn (Recipient $recipient): bool => $this->matchesSearch([
                $recipient->name,
                $recipient->endpoint,
                $recipient->endpointTarget(),
                $recipient->endpointTypeLabel(),
                $recipient->isWebhookEndpoint() ? $recipient->webhookAuthenticationSummary() : 'Not required',
                $recipient->additionalHeadersSummary(),
                collect($recipient->configuredAdditionalHeaders())->pluck('name')->all(),
                collect($recipient->configuredAdditionalHeaders())->pluck('value')->all(),
                $recipient->groups->pluck('name')->all(),
            ]))
            ->values();
    }

    #[Computed]
    public function groups()
    {
        return RecipientGroup::query()
            ->withCount('recipients')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function endpointTypeOptions(): array
    {
        return $this->recipientEndpointTypeOptions();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function webhookAuthenticationOptions(): array
    {
        return $this->recipientWebhookAuthenticationOptions();
    }

    #[Computed]
    public function formEndpointType(): string
    {
        return $this->endpointType;
    }

    public function saveRecipient(): void
    {
        $validated = $this->validate($this->recipientRules());

        $recipient = Recipient::query()->updateOrCreate(
            ['id' => $this->editingRecipientId],
            RecipientData::payload($validated),
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        $this->resetRecipientForm();
        $this->dispatch('recipient-saved');
    }

    public function editRecipient(int $recipientId): void
    {
        $recipient = Recipient::query()
            ->with('groups:id')
            ->findOrFail($recipientId);

        ['type' => $endpointType, 'target' => $endpointTarget] = RecipientData::parseEndpoint($recipient->endpoint);

        $this->editingRecipientId = $recipient->id;
        $this->name = $recipient->name;
        $this->endpointType = $endpointType;
        $this->endpointTarget = $endpointTarget;
        $this->webhookAuthType = $recipient->webhook_auth_type;
        $this->webhookAuthUsername = $recipient->webhook_auth_username ?? '';
        $this->webhookAuthPassword = $recipient->webhook_auth_password ?? '';
        $this->webhookAuthToken = $recipient->webhook_auth_token ?? '';
        $this->webhookAuthHeaderName = $recipient->webhook_auth_header_name ?? '';
        $this->webhookAuthHeaderValue = $recipient->webhook_auth_header_value ?? '';
        $this->additionalHeaders = $recipient->configuredAdditionalHeaders();
        $this->selectedGroupIds = $recipient->groups
            ->pluck('id')
            ->map(fn (int $groupId): string => (string) $groupId)
            ->all();

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'recipient');
    }

    public function confirmRecipientDeletion(int $recipientId): void
    {
        $recipient = Recipient::query()->findOrFail($recipientId);

        $this->deleteConfirmationType = 'recipient';
        $this->deleteConfirmationId = $recipient->id;
        $this->deleteConfirmationName = $recipient->name;
        $this->showDeleteConfirmationModal = true;
    }

    public function cancelRecipientEditing(): void
    {
        $this->resetRecipientForm();
    }

    public function updatedEndpointType(string $endpointType): void
    {
        if ($endpointType !== Recipient::TYPE_WEBHOOK) {
            $this->resetWebhookAuthentication();
        }

        $this->resetValidation();
    }

    public function addAdditionalHeader(): void
    {
        $this->additionalHeaders[] = [
            'name' => '',
            'value' => '',
        ];
    }

    public function removeAdditionalHeader(int $index): void
    {
        unset($this->additionalHeaders[$index]);

        $this->additionalHeaders = array_values($this->additionalHeaders);
    }

    public function deleteConfirmedItem(): void
    {
        if ($this->deleteConfirmationType === 'recipient' && $this->deleteConfirmationId !== null) {
            Recipient::query()->findOrFail($this->deleteConfirmationId)->delete();

            if ($this->editingRecipientId === $this->deleteConfirmationId) {
                $this->resetRecipientForm();
            }

            $this->dispatch('recipient-deleted');
        }

        $this->closeDeleteConfirmation();
    }

    public function cancelDeleteConfirmation(): void
    {
        $this->closeDeleteConfirmation();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function recipientRules(): array
    {
        $this->additionalHeaders = ServiceData::normalizeAdditionalHeaders($this->additionalHeaders);

        return $this->recipientValidationRules($this->endpointType, $this->webhookAuthType);
    }

    private function resetRecipientForm(): void
    {
        $this->reset([
            'editingRecipientId',
            'name',
            'endpointTarget',
            'webhookAuthUsername',
            'webhookAuthPassword',
            'webhookAuthToken',
            'webhookAuthHeaderName',
            'webhookAuthHeaderValue',
            'additionalHeaders',
            'selectedGroupIds',
        ]);

        $this->endpointType = Recipient::TYPE_MAIL;
        $this->webhookAuthType = Recipient::WEBHOOK_AUTH_NONE;

        $this->resetValidation();
    }

    private function resetWebhookAuthentication(): void
    {
        $this->webhookAuthType = Recipient::WEBHOOK_AUTH_NONE;
        $this->webhookAuthUsername = '';
        $this->webhookAuthPassword = '';
        $this->webhookAuthToken = '';
        $this->webhookAuthHeaderName = '';
        $this->webhookAuthHeaderValue = '';
        $this->additionalHeaders = [];
    }

    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
        $this->deleteConfirmationType = null;
        $this->deleteConfirmationId = null;
        $this->deleteConfirmationName = '';
    }

    private function searchTerm(): string
    {
        return Str::lower(trim($this->search));
    }

    /**
     * @param  array<int, mixed>  $segments
     */
    private function matchesSearch(array $segments): bool
    {
        $search = $this->searchTerm();

        if ($search === '') {
            return true;
        }

        $haystack = Str::of(collect($segments)->flatten()->filter()->implode(' '))->lower();

        return $haystack->contains($search);
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Recipients') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Manage email and webhook recipients, assign them to groups, and configure webhook authentication when needed.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('recipient-groups.index')" wire:navigate>
                {{ __('Manage recipient groups') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div
        x-data="{ isStuck: false, updateStickyState() { this.isStuck = this.$el.getBoundingClientRect().top <= 16 && window.scrollY > 0; } }"
        x-init="updateStickyState()"
        x-on:scroll.window.throttle.50ms="updateStickyState()"
        x-on:resize.window.throttle.50ms="updateStickyState()"
        :class="isStuck ? 'shadow-lg shadow-zinc-900/10 dark:shadow-black/30' : 'shadow-sm'"
        class="sticky top-4 z-20 mb-6 rounded-xl border border-zinc-200 bg-white/95 p-4 backdrop-blur transition-shadow duration-200 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900/95"
    >
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search recipients')"
            type="search"
            :placeholder="__('Search by name, endpoint, authentication, or group')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <div
                x-data="{ highlight: false, timeout: null, focusForm() { this.$el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' }); this.$nextTick(() => this.$el.querySelector('input, select, textarea, button')?.focus({ preventScroll: true })); this.highlight = true; if (this.timeout) { clearTimeout(this.timeout); } this.timeout = setTimeout(() => { this.highlight = false }, 2200); } }"
                x-on:focus-form.window="if ($event.detail.form === 'recipient') { focusForm() }"
                :class="{ 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white shadow-lg shadow-sky-500/10 animate-pulse dark:ring-sky-300/60 dark:ring-offset-zinc-900': highlight }"
                class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $editingRecipientId ? __('Edit recipient') : __('Create recipient') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Choose how the destination should be contacted, then enter the mailbox or webhook target without the internal storage prefix.') }}</flux:subheading>
                    </div>

                    <x-action-message on="recipient-saved">{{ __('Recipient saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveRecipient" class="mt-6 min-w-0 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="min-w-0">
                        <flux:input wire:model="name" :label="__('Name')" type="text" required />
                    </div>

                    <div class="min-w-0">
                        <label for="endpointType" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Protocol') }}</label>
                        <select
                            id="endpointType"
                            wire:model.live="endpointType"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            @foreach ($this->endpointTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                        @error('endpointType')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="min-w-0">
                    <flux:input
                        wire:model="endpointTarget"
                        :label="__($this->formEndpointType === \App\Models\Recipient::TYPE_MAIL ? 'Email address' : 'Webhook destination')"
                        type="text"
                        required
                        :placeholder="$this->formEndpointType === \App\Models\Recipient::TYPE_MAIL ? 'alerts@example.com' : 'hooks.example.com/services/pager-duty'"
                    />
                    @error('endpointTarget')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($this->formEndpointType === \App\Models\Recipient::TYPE_MAIL)
                            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {{ __('Email recipient') }}
                            </span>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Enter an email address.') }}</p>
                        @elseif ($this->formEndpointType === \App\Models\Recipient::TYPE_WEBHOOK)
                            <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                {{ __('Webhook recipient') }}
                            </span>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Enter a full http:// or https:// URL.') }}</p>
                        @else
                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                {{ __('Choose a protocol') }}
                            </span>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Choose a protocol first, then enter the destination without the internal prefix.') }}</p>
                        @endif
                    </div>
                </div>

                @if ($this->formEndpointType === \App\Models\Recipient::TYPE_WEBHOOK)
                    <div class="min-w-0 space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Webhook authentication') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Choose how this webhook should authenticate when it is called.') }}</flux:subheading>
                        </div>

                        <div>
                            <label for="webhookAuthType" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Authentication type') }}</label>
                            <select
                                id="webhookAuthType"
                                wire:model.live="webhookAuthType"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            >
                                @foreach ($this->webhookAuthenticationOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('webhookAuthType')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_BEARER)
                            <flux:input wire:model="webhookAuthToken" :label="__('Bearer token')" type="password" autocomplete="off" viewable />
                        @elseif ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_BASIC)
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="min-w-0">
                                    <flux:input wire:model="webhookAuthUsername" :label="__('Username')" type="text" autocomplete="off" />
                                </div>
                                <div class="min-w-0">
                                    <flux:input wire:model="webhookAuthPassword" :label="__('Password')" type="password" autocomplete="off" viewable />
                                </div>
                            </div>
                        @elseif ($webhookAuthType === \App\Models\Recipient::WEBHOOK_AUTH_HEADER)
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="min-w-0">
                                    <flux:input wire:model="webhookAuthHeaderName" :label="__('Header name')" type="text" placeholder="X-Webhook-Token" autocomplete="off" />
                                </div>
                                <div class="min-w-0">
                                    <flux:input wire:model="webhookAuthHeaderValue" :label="__('Header value')" type="password" autocomplete="off" viewable />
                                </div>
                            </div>
                        @endif

                        <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <flux:heading>{{ __('Additional headers') }}</flux:heading>
                                    <flux:subheading class="mt-1">{{ __('Optionally send extra headers with every webhook delivery, similar to service request headers.') }}</flux:subheading>
                                </div>

                                <flux:button type="button" variant="subtle" size="sm" wire:click="addAdditionalHeader">
                                    {{ __('Add header') }}
                                </flux:button>
                            </div>

                            @if ($additionalHeaders === [])
                                <p class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                    {{ __('This webhook will only send authentication headers unless you add more here.') }}
                                </p>
                            @else
                                <div class="space-y-3">
                                    @foreach ($additionalHeaders as $index => $header)
                                        <div wire:key="recipient-additional-header-{{ $index }}" class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] dark:border-zinc-700 dark:bg-zinc-950/40">
                                            <div class="min-w-0">
                                                <flux:input wire:model="additionalHeaders.{{ $index }}.name" :label="__('Header name')" type="text" placeholder="X-Environment" autocomplete="off" />
                                            </div>
                                            <div class="min-w-0">
                                                <flux:input wire:model="additionalHeaders.{{ $index }}.value" :label="__('Header value')" type="text" placeholder="production" autocomplete="off" />
                                            </div>
                                            <div class="flex items-end">
                                                <flux:button type="button" variant="ghost" wire:click="removeAdditionalHeader({{ $index }})">{{ __('Remove') }}</flux:button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('additionalHeaders.*.name')
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                @error('additionalHeaders.*.value')
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    </div>
                @endif

                <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading>{{ __('Groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Recipients can belong to multiple groups at once.') }}</flux:subheading>
                        </div>

                        <flux:button variant="subtle" size="sm" :href="route('recipient-groups.index')" wire:navigate>
                            {{ __('Open groups') }}
                        </flux:button>
                    </div>

                    @if ($this->groups->isEmpty())
                        <p class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            {{ __('Create a group from the Recipient Groups page and it will appear here for assignment.') }}
                        </p>
                    @else
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($this->groups as $group)
                                <label wire:key="group-option-{{ $group->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $group->name }}</span>
                                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice('{0} No recipients|{1} :count recipient|[2,*] :count recipients', $group->recipients_count, ['count' => $group->recipients_count]) }}
                                        </span>
                                    </span>
                                    <input
                                        wire:model="selectedGroupIds"
                                        type="checkbox"
                                        value="{{ $group->id }}"
                                        class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                    >
                                </label>
                            @endforeach
                        </div>
                        @error('selectedGroupIds.*')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                        <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                            {{ $editingRecipientId ? __('Save recipient') : __('Create recipient') }}
                        </flux:button>

                        @if ($editingRecipientId)
                            <flux:button type="button" variant="ghost" wire:click="cancelRecipientEditing" class="w-full sm:w-auto">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Manage recipients') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review endpoints, confirm their authentication settings, and move recipients between groups.') }}</flux:subheading>
                </div>

                <x-action-message on="recipient-deleted">{{ __('Recipient removed.') }}</x-action-message>
            </div>

            @if ($this->recipients->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No recipients match your search.') : __('No recipients have been created yet.') }}
                </p>
            @else
                <div class="mt-6 max-w-full overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                <th class="pb-3 font-medium">{{ __('Recipient') }}</th>
                                <th class="pb-3 font-medium">{{ __('Endpoint') }}</th>
                                <th class="pb-3 font-medium">{{ __('Authentication') }}</th>
                                <th class="pb-3 font-medium">{{ __('Extra headers') }}</th>
                                <th class="pb-3 font-medium">{{ __('Groups') }}</th>
                                <th class="pb-3 font-medium">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($this->recipients as $recipient)
                                <tr wire:key="recipient-{{ $recipient->id }}" class="align-top">
                                    <td class="py-4 pe-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $recipient->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Saved as :endpoint', ['endpoint' => $recipient->endpoint]) }}
                                        </div>
                                    </td>
                                    <td class="py-4 pe-4">
                                        <div class="flex flex-col gap-2">
                                            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-medium {{ $recipient->isMailEndpoint() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' }}">
                                                {{ __($recipient->endpointTypeLabel()) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                        {{ __($recipient->isWebhookEndpoint() ? $recipient->webhookAuthenticationSummary() : 'Not required') }}
                                    </td>
                                    <td class="py-4 pe-4 text-zinc-600 dark:text-zinc-300">
                                        {{ __($recipient->isWebhookEndpoint() ? $recipient->additionalHeadersSummary() : 'Not required') }}
                                    </td>
                                    <td class="py-4 pe-4">
                                        @if ($recipient->groups->isEmpty())
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('No groups') }}</span>
                                        @else
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($recipient->groups as $group)
                                                    <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                        {{ $group->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button type="button" variant="ghost" wire:click="editRecipient({{ $recipient->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button type="button" variant="danger" wire:click="confirmRecipientDeletion({{ $recipient->id }})">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showDeleteConfirmationModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this?') }}</flux:heading>
                <flux:subheading class="mt-2">
                    @if ($deleteConfirmationType === 'recipient')
                        {{ __('This will permanently delete the recipient ":name".', ['name' => $deleteConfirmationName]) }}
                    @else
                        {{ __('This action cannot be undone.') }}
                    @endif
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
