<?php

use App\Concerns\ServiceValidation;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Support\Services\ServiceData;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service management')] class extends Component {
    use ServiceValidation;

    public string $search = '';

    public ?int $editingServiceId = null;

    public string $name = '';

    public string $url = '';

    public int $intervalSeconds = Service::INTERVAL_1_MINUTE;

    public string $expectType = Service::EXPECT_NONE;

    public string $expectValue = '';

    /** @var array<int, string> */
    public array $selectedServiceGroupIds = [];

    /** @var array<int, string> */
    public array $selectedRecipientGroupIds = [];

    /** @var array<int, string> */
    public array $selectedRecipientIds = [];

    public bool $showDeleteConfirmationModal = false;

    public ?string $deleteConfirmationType = null;

    public ?int $deleteConfirmationId = null;

    public string $deleteConfirmationName = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    /**
     * Get the monitored services.
     */
    #[Computed]
    public function services()
    {
        $services = Service::query()
            ->with([
                'recipients:id,name,endpoint',
                'recipientGroups:id,name',
                'recipientGroups.recipients:id,name,endpoint',
                'groups:id,name',
                'groups.recipients:id,name,endpoint',
                'groups.recipientGroups:id,name',
                'groups.recipientGroups.recipients:id,name,endpoint',
            ])
            ->orderBy('name')
            ->orderBy('url')
            ->get();

        if ($this->searchTerm() === '') {
            return $services;
        }

        return $services
            ->filter(function (Service $service): bool {
                $effectiveRecipients = $service->effectiveRecipientRoutes();

                return $this->matchesSearch([
                    $service->name,
                    $service->url,
                    $service->intervalLabel(),
                    $service->expectSummary(),
                    $service->monitoringStatusLabel(),
                    $service->statusDurationSummary(),
                    $service->monitoringReasonSummary(),
                    $service->nextCheckSummary(),
                    $service->groups->pluck('name')->all(),
                    $service->recipientGroups->pluck('name')->all(),
                    $service->recipients->pluck('name')->all(),
                    $effectiveRecipients->map(fn (array $route): string => $route['recipient']->name)->all(),
                    $effectiveRecipients->flatMap(fn (array $route): array => $route['sources'])->all(),
                ]);
            })
            ->values();
    }

    /**
     * Get the service groups available for assignment and review.
     */
    #[Computed]
    public function serviceGroups()
    {
        return ServiceGroup::query()
            ->with([
                'recipients:id,name,endpoint',
                'recipientGroups:id,name',
            ])
            ->withCount(['services', 'recipients', 'recipientGroups'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the recipients available for routing.
     */
    #[Computed]
    public function recipients()
    {
        return Recipient::query()
            ->orderBy('name')
            ->orderBy('endpoint')
            ->get();
    }

    /**
     * Get the recipient groups available for routing.
     */
    #[Computed]
    public function recipientGroups()
    {
        return RecipientGroup::query()
            ->withCount('recipients')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the supported interval options.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function intervalOptions(): array
    {
        return $this->serviceIntervalOptions();
    }

    /**
     * Get the supported expect field options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function expectTypeOptions(): array
    {
        return $this->serviceExpectationOptions();
    }

    /**
     * Create or update a service.
     */
    public function saveService(): void
    {
        $validated = $this->validate($this->serviceRules());

        $service = Service::query()->updateOrCreate(
            ['id' => $this->editingServiceId],
            $this->servicePayload($validated),
        );

        $service->groups()->sync($validated['selectedServiceGroupIds'] ?? []);
        $service->recipientGroups()->sync($validated['selectedRecipientGroupIds'] ?? []);
        $service->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        $this->resetServiceForm();
        $this->dispatch('service-saved');
    }

    /**
     * Populate the form for editing.
     */
    public function editService(int $serviceId): void
    {
        $service = Service::query()
            ->with(['groups:id', 'recipientGroups:id', 'recipients:id'])
            ->findOrFail($serviceId);

        $this->editingServiceId = $service->id;
        $this->name = $service->name;
        $this->url = $service->url;
        $this->intervalSeconds = $service->interval_seconds;
        $this->expectType = $service->expect_type ?? Service::EXPECT_NONE;
        $this->expectValue = $service->expect_value ?? '';
        $this->selectedServiceGroupIds = $service->groups->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->selectedRecipientGroupIds = $service->recipientGroups->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->selectedRecipientIds = $service->recipients->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'service');
    }

    /**
     * Prompt to delete a service.
     */
    public function confirmServiceDeletion(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);

        $this->promptDeleteConfirmation('service', $service->id, $service->name);
    }

    /**
     * Cancel service editing.
     */
    public function cancelServiceEditing(): void
    {
        $this->resetServiceForm();
    }

    /**
     * Update the form when the expectation mode changes.
     */
    public function updatedExpectType(string $expectType): void
    {
        if ($expectType === Service::EXPECT_NONE) {
            $this->expectValue = '';
        }

        $this->resetValidation();
    }

    /**
     * Delete the record selected in the confirmation modal.
     */
    public function deleteConfirmedItem(): void
    {
        match ($this->deleteConfirmationType) {
            'service' => $this->deleteService($this->deleteConfirmationId),
            default => null,
        };

        $this->closeDeleteConfirmation();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function cancelDeleteConfirmation(): void
    {
        $this->closeDeleteConfirmation();
    }

    /**
     * Get the validation rules for services.
     *
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(): array
    {
        return $this->serviceValidationRules($this->expectType);
    }

    /**
     * Build the persistence payload for a service.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function servicePayload(array $validated): array
    {
        return ServiceData::payload($validated);
    }

    /**
     * Reset the service form to its default state.
     */
    private function resetServiceForm(): void
    {
        $this->reset([
            'editingServiceId',
            'name',
            'url',
            'expectValue',
            'selectedServiceGroupIds',
            'selectedRecipientGroupIds',
            'selectedRecipientIds',
        ]);

        $this->intervalSeconds = Service::INTERVAL_1_MINUTE;
        $this->expectType = Service::EXPECT_NONE;
        $this->resetValidation();
    }

    /**
     * Open the confirmation modal for the selected record.
     */
    private function promptDeleteConfirmation(string $type, int $id, string $name): void
    {
        $this->deleteConfirmationType = $type;
        $this->deleteConfirmationId = $id;
        $this->deleteConfirmationName = $name;
        $this->showDeleteConfirmationModal = true;
    }

    /**
     * Close and reset the confirmation modal state.
     */
    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
        $this->deleteConfirmationType = null;
        $this->deleteConfirmationId = null;
        $this->deleteConfirmationName = '';
    }

    /**
     * Delete a service record.
     */
    private function deleteService(?int $serviceId): void
    {
        if ($serviceId === null) {
            return;
        }

        Service::query()->findOrFail($serviceId)->delete();

        if ($this->editingServiceId === $serviceId) {
            $this->resetServiceForm();
        }

        $this->dispatch('service-deleted');
    }

    /**
     * Get the normalized search term.
     */
    private function searchTerm(): string
    {
        return Str::lower(trim($this->search));
    }

    /**
     * Determine whether the provided values match the current search term.
     *
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

<section wire:poll.5s.visible class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Services') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create monitored services, set their polling interval and expectation rules, then route alerts through direct recipients, recipient groups, and reusable service groups.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('service-groups.index')" wire:navigate>
                {{ __('Manage service groups') }}
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
            :label="__('Search services')"
            type="search"
            :placeholder="__('Search by name, URL, routing, or related recipients')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <div
                x-data="{ highlight: false, timeout: null, focusForm() { this.$el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' }); this.$nextTick(() => this.$el.querySelector('input, select, textarea, button')?.focus({ preventScroll: true })); this.highlight = true; if (this.timeout) { clearTimeout(this.timeout); } this.timeout = setTimeout(() => { this.highlight = false }, 2200); } }"
                x-on:focus-form.window="if ($event.detail.form === 'service') { focusForm() }"
                :class="{ 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white shadow-lg shadow-sky-500/10 animate-pulse dark:ring-sky-300/60 dark:ring-offset-zinc-900': highlight }"
                class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $editingServiceId ? __('Edit service') : __('Create service') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Define what to monitor, how often to check it, and exactly where notifications should route.') }}</flux:subheading>
                    </div>

                    <x-action-message on="service-saved">{{ __('Service saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveService" class="mt-6 space-y-5">
                    <flux:input wire:model="name" :label="__('Name')" type="text" required placeholder="Marketing site" />
                    <flux:input wire:model="url" :label="__('URL')" type="text" required placeholder="status.example.com" />

                    <div>
                        <label for="intervalSeconds" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Interval') }}</label>
                        <select
                            id="intervalSeconds"
                            wire:model="intervalSeconds"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            @foreach ($this->intervalOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('intervalSeconds')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Expectation') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Optionally look for plain text or a regex pattern in the returned HTML before treating the service as healthy.') }}</flux:subheading>
                        </div>

                        <div>
                            <label for="expectType" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Expect type') }}</label>
                            <select
                                id="expectType"
                                wire:model.live="expectType"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none transition focus:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            >
                                @foreach ($this->expectTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('expectType')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($expectType !== \App\Models\Service::EXPECT_NONE)
                            <flux:textarea
                                wire:model="expectValue"
                                :label="__('Expect value')"
                                rows="3"
                                :placeholder="$expectType === \App\Models\Service::EXPECT_REGEX ? '/healthy/i' : 'All systems operational'"
                            />

                            @if ($expectType === \App\Models\Service::EXPECT_REGEX)
                                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Use a valid PHP regex with delimiters, for example /healthy/i.') }}</p>
                            @endif
                        @else
                            <p class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Leave the expectation disabled if a simple successful response is enough.') }}</p>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                            <flux:heading>{{ __('Service groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Attach reusable routing bundles that already contain their own recipients and recipient groups.') }}</flux:subheading>
                            </div>

                            <flux:button variant="subtle" size="sm" :href="route('service-groups.index')" wire:navigate>
                                {{ __('Open service groups') }}
                            </flux:button>
                        </div>

                        @if ($this->serviceGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create a service group from the Service Groups page and it will appear here for assignment.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->serviceGroups as $serviceGroup)
                                    <label wire:key="service-group-option-{{ $serviceGroup->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $serviceGroup->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ trans_choice('{0} No direct recipients|{1} :count direct recipient|[2,*] :count direct recipients', $serviceGroup->recipients_count, ['count' => $serviceGroup->recipients_count]) }}
                                                {{ __('and') }}
                                                {{ trans_choice('{0} no recipient groups|{1} :count recipient group|[2,*] :count recipient groups', $serviceGroup->recipient_groups_count, ['count' => $serviceGroup->recipient_groups_count]) }}
                                            </span>
                                        </div>

                                        <input
                                            wire:model="selectedServiceGroupIds"
                                            type="checkbox"
                                            value="{{ $serviceGroup->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div>
                            <flux:heading>{{ __('Recipient groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Assign reusable recipient groups directly to this service.') }}</flux:subheading>
                        </div>

                        @if ($this->recipientGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipient groups first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipientGroups as $recipientGroup)
                                    <label wire:key="service-recipient-group-option-{{ $recipientGroup->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $recipientGroup->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No recipients|{1} :count recipient|[2,*] :count recipients', $recipientGroup->recipients_count, ['count' => $recipientGroup->recipients_count]) }}</span>
                                        </div>

                                        <input
                                            wire:model="selectedRecipientGroupIds"
                                            type="checkbox"
                                            value="{{ $recipientGroup->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div>
                            <flux:heading>{{ __('Direct recipients') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Add recipients directly when they should be routed to this service even without a group.') }}</flux:subheading>
                        </div>

                        @if ($this->recipients->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipients first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipients as $recipient)
                                    <label wire:key="service-recipient-option-{{ $recipient->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $recipient->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __($recipient->endpointTypeLabel()) }} · {{ $recipient->endpointTarget() }}</span>
                                        </div>

                                        <input
                                            wire:model="selectedRecipientIds"
                                            type="checkbox"
                                            value="{{ $recipient->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <flux:button variant="primary" type="submit">{{ $editingServiceId ? __('Save service') : __('Create service') }}</flux:button>

                        @if ($editingServiceId)
                            <flux:button type="button" variant="ghost" wire:click="cancelServiceEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Managed services') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review every unique recipient a service resolves to, including exactly whether it comes from a direct assignment, a recipient group, or a service group.') }}</flux:subheading>
                </div>

                <x-action-message on="service-deleted">{{ __('Service removed.') }}</x-action-message>
            </div>

            @if ($this->services->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No services match your search.') : __('No services have been created yet.') }}
                </p>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($this->services as $service)
                        @php($effectiveRecipients = $service->effectiveRecipientRoutes())

                        <details
                            wire:key="service-row-{{ $service->id }}"
                            x-data="{ expanded: false }"
                            x-bind:open="expanded"
                            x-on:toggle="expanded = $el.open"
                            class="group rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40"
                        >
                            <summary class="list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 space-y-2">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $service->name }}</div>
                                        <div class="break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $service->url }}</div>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <span class="rounded-full px-3 py-1 font-medium {{ $service->monitoringStatusClasses() }}">{{ __($service->monitoringStatusLabel()) }}</span>
                                            <span class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ $service->intervalLabel() }}</span>
                                            <span class="rounded-full bg-zinc-200 px-3 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $service->expectSummary() }}</span>
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                {{ trans_choice('{1} :count unique recipient|[2,*] :count unique recipients', $effectiveRecipients->count(), ['count' => $effectiveRecipients->count()]) }}
                                            </span>
                                            <span
                                                x-data="window.serviceCheckTimer(@js($service->next_check_at?->toIso8601String()))"
                                                x-init="init()"
                                                x-on:livewire:navigating.window="destroy()"
                                                class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                            >
                                                <span x-text="remainingLabel">{{ $service->nextCheckSummary() }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                        <span class="font-medium">{{ __('Monitoring and routing details') }}</span>
                                        <span class="rounded-full border border-zinc-300 px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] dark:border-zinc-600">
                                            {{ __('Expand') }}
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expand to review monitoring status, the next check timer, routing details, and effective recipients.') }}</p>
                            </summary>

                            <div class="mt-4 space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Monitoring and routing summary') }}</div>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review the most recent monitoring outcome, the next scheduled check, and every recipient that will be notified when the status changes.') }}</p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button type="button" variant="ghost" wire:click="editService({{ $service->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button type="button" variant="danger" wire:click="confirmServiceDeletion({{ $service->id }})">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-4">
                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Current status') }}</div>
                                        <div class="mt-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $service->monitoringStatusClasses() }}">{{ __($service->monitoringStatusLabel()) }}</span>
                                            @if ($service->statusDurationSummary())
                                                <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Status duration: :duration', ['duration' => $service->statusDurationSummary()]) }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Next check') }}</div>
                                        <div
                                            x-data="window.serviceCheckTimer(@js($service->next_check_at?->toIso8601String()))"
                                            x-init="init()"
                                            x-on:livewire:navigating.window="destroy()"
                                            class="mt-3 text-sm text-zinc-600 dark:text-zinc-300"
                                        >
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100" x-text="remainingLabel">{{ $service->nextCheckSummary() }}</div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $service->next_check_at?->toDayDateTimeString() ?? __('Waiting to be scheduled') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Last checked') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $service->last_checked_at?->diffForHumans() ?? __('Not checked yet') }}
                                        </div>
                                        @if ($service->last_checked_at)
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $service->last_checked_at->toDayDateTimeString() }}</div>
                                        @endif
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Latest reason') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $service->monitoringReasonSummary() }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-3">
                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Service groups') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($service->groups->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($service->groups as $serviceGroup)
                                                    <span wire:key="service-group-chip-{{ $service->id }}-{{ $serviceGroup->id }}" class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $serviceGroup->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Direct recipient groups') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($service->recipientGroups->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($service->recipientGroups as $recipientGroup)
                                                    <span wire:key="service-recipient-group-chip-{{ $service->id }}-{{ $recipientGroup->id }}" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $recipientGroup->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Direct recipients') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($service->recipients->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($service->recipients as $recipient)
                                                    <span wire:key="service-recipient-chip-{{ $service->id }}-{{ $recipient->id }}" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $recipient->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Effective recipients') }}</div>

                                    @if ($effectiveRecipients->isEmpty())
                                        <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No recipients resolve from this service yet.') }}</p>
                                    @else
                                        <div class="grid gap-3 lg:grid-cols-2">
                                            @foreach ($effectiveRecipients as $route)
                                                <div wire:key="effective-recipient-{{ $service->id }}-{{ $route['recipient']->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $route['recipient']->name }}</div>
                                                            <div class="mt-1 break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $route['recipient']->endpointTarget() }}</div>
                                                        </div>

                                                        <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ __($route['recipient']->endpointTypeLabel()) }}</span>
                                                    </div>

                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($route['sources'] as $source)
                                                            <span wire:key="effective-recipient-source-{{ $service->id }}-{{ $route['recipient']->id }}-{{ md5($source) }}" class="rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ __($source) }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showDeleteConfirmationModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Confirm deletion') }}</flux:heading>
                <flux:subheading class="mt-2">
                    @if ($deleteConfirmationType === 'service')
                        {{ __('This will permanently delete the service ":name".', ['name' => $deleteConfirmationName]) }}
                    @endif
                </flux:subheading>
            </div>

            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Linked assignments will be removed with it, but recipients, recipient groups, and service groups themselves will stay in place.') }}</p>

            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
