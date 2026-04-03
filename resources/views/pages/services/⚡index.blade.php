<?php

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service management')] class extends Component {
    public ?int $editingServiceId = null;

    public ?int $editingServiceGroupId = null;

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

    public string $groupName = '';

    /** @var array<int, string> */
    public array $groupSelectedRecipientGroupIds = [];

    /** @var array<int, string> */
    public array $groupSelectedRecipientIds = [];

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
        return Service::query()
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
        return Service::intervalOptions();
    }

    /**
     * Get the supported expect field options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function expectTypeOptions(): array
    {
        return Service::expectTypes();
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
     * Create or update a service group.
     */
    public function saveServiceGroup(): void
    {
        $validated = $this->validate($this->serviceGroupRules());

        $serviceGroup = ServiceGroup::query()->updateOrCreate(
            ['id' => $this->editingServiceGroupId],
            ['name' => trim($validated['groupName'])],
        );

        $serviceGroup->recipientGroups()->sync($validated['groupSelectedRecipientGroupIds'] ?? []);
        $serviceGroup->recipients()->sync($validated['groupSelectedRecipientIds'] ?? []);

        $this->resetServiceGroupForm();
        $this->dispatch('service-group-saved');
    }

    /**
     * Populate the service group form for editing.
     */
    public function editServiceGroup(int $serviceGroupId): void
    {
        $serviceGroup = ServiceGroup::query()
            ->with(['recipientGroups:id', 'recipients:id'])
            ->findOrFail($serviceGroupId);

        $this->editingServiceGroupId = $serviceGroup->id;
        $this->groupName = $serviceGroup->name;
        $this->groupSelectedRecipientGroupIds = $serviceGroup->recipientGroups->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->groupSelectedRecipientIds = $serviceGroup->recipients->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'service-group');
    }

    /**
     * Prompt to delete a service group.
     */
    public function confirmServiceGroupDeletion(int $serviceGroupId): void
    {
        $serviceGroup = ServiceGroup::query()->findOrFail($serviceGroupId);

        $this->promptDeleteConfirmation('service-group', $serviceGroup->id, $serviceGroup->name);
    }

    /**
     * Cancel service group editing.
     */
    public function cancelServiceGroupEditing(): void
    {
        $this->resetServiceGroupForm();
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
            'service-group' => $this->deleteServiceGroup($this->deleteConfirmationId),
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail(__('The URL must be a string.'));

                        return;
                    }

                    if (! filter_var($this->normalizeUrl($value), FILTER_VALIDATE_URL)) {
                        $fail(__('Service URLs must use the format example.com/status or https://example.com/status.'));
                    }
                },
            ],
            'intervalSeconds' => ['required', 'integer', Rule::in(array_keys($this->intervalOptions()))],
            'expectType' => ['required', Rule::in(array_keys($this->expectTypeOptions()))],
            'expectValue' => [
                Rule::requiredIf(fn (): bool => $this->expectType !== Service::EXPECT_NONE),
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->expectType !== Service::EXPECT_REGEX || blank($value)) {
                        return;
                    }

                    if (! is_string($value) || @preg_match($value, '') === false) {
                        $fail(__('Regular expressions must be valid PHP patterns including delimiters, for example /healthy/i.'));
                    }
                },
            ],
            'selectedServiceGroupIds' => ['array'],
            'selectedServiceGroupIds.*' => ['integer', Rule::exists('service_groups', 'id')],
            'selectedRecipientGroupIds' => ['array'],
            'selectedRecipientGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'selectedRecipientIds' => ['array'],
            'selectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }

    /**
     * Get the validation rules for service groups.
     *
     * @return array<string, array<int, mixed>>
     */
    private function serviceGroupRules(): array
    {
        return [
            'groupName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_groups', 'name')->ignore($this->editingServiceGroupId),
            ],
            'groupSelectedRecipientGroupIds' => ['array'],
            'groupSelectedRecipientGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'groupSelectedRecipientIds' => ['array'],
            'groupSelectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }

    /**
     * Normalize the submitted URL.
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://'.ltrim($url, '/');
    }

    /**
     * Build the persistence payload for a service.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function servicePayload(array $validated): array
    {
        $expectType = $validated['expectType'];
        $expectValue = trim((string) ($validated['expectValue'] ?? ''));

        return [
            'name' => trim($validated['name']),
            'url' => $this->normalizeUrl($validated['url']),
            'interval_seconds' => (int) $validated['intervalSeconds'],
            'expect_type' => $expectType === Service::EXPECT_NONE ? null : $expectType,
            'expect_value' => $expectType === Service::EXPECT_NONE || $expectValue === '' ? null : $expectValue,
        ];
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
     * Reset the service group form to its default state.
     */
    private function resetServiceGroupForm(): void
    {
        $this->reset([
            'editingServiceGroupId',
            'groupName',
            'groupSelectedRecipientGroupIds',
            'groupSelectedRecipientIds',
        ]);

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
     * Delete a service group record.
     */
    private function deleteServiceGroup(?int $serviceGroupId): void
    {
        if ($serviceGroupId === null) {
            return;
        }

        ServiceGroup::query()->findOrFail($serviceGroupId)->delete();

        $this->selectedServiceGroupIds = array_values(array_filter(
            $this->selectedServiceGroupIds,
            fn (string $selectedServiceGroupId): bool => (int) $selectedServiceGroupId !== $serviceGroupId,
        ));

        if ($this->editingServiceGroupId === $serviceGroupId) {
            $this->resetServiceGroupForm();
        }

        $this->dispatch('service-group-deleted');
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Services') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Create monitored services, set their polling interval and expectation rules, then route alerts through direct recipients, recipient groups, and reusable service groups.') }}</flux:subheading>
        <flux:separator variant="subtle" />
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
                        <div>
                            <flux:heading>{{ __('Service groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Attach reusable routing bundles that already contain their own recipients and recipient groups.') }}</flux:subheading>
                        </div>

                        @if ($this->serviceGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create a service group below and it will appear here for assignment.') }}</p>
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
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No services have been created yet.') }}</p>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($this->services as $service)
                        @php($effectiveRecipients = $service->effectiveRecipientRoutes())

                        <div wire:key="service-row-{{ $service->id }}" class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $service->name }}</div>
                                    <div class="break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $service->url }}</div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <span class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ $service->intervalLabel() }}</span>
                                        <span class="rounded-full bg-zinc-200 px-3 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $service->expectSummary() }}</span>
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                            {{ trans_choice('{1} :count unique recipient|[2,*] :count unique recipients', $effectiveRecipients->count(), ['count' => $effectiveRecipients->count()]) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:button type="button" variant="ghost" wire:click="editService({{ $service->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button type="button" variant="danger" wire:click="confirmServiceDeletion({{ $service->id }})">{{ __('Delete') }}</flux:button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-3">
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

                            <div class="mt-4">
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
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <div
                x-data="{ highlight: false, timeout: null, focusForm() { this.$el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' }); this.$nextTick(() => this.$el.querySelector('input, select, textarea, button')?.focus({ preventScroll: true })); this.highlight = true; if (this.timeout) { clearTimeout(this.timeout); } this.timeout = setTimeout(() => { this.highlight = false }, 2200); } }"
                x-on:focus-form.window="if ($event.detail.form === 'service-group') { focusForm() }"
                :class="{ 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white shadow-lg shadow-sky-500/10 animate-pulse dark:ring-sky-300/60 dark:ring-offset-zinc-900': highlight }"
                class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $editingServiceGroupId ? __('Edit service group') : __('Create service group') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Bundle recipients and recipient groups once, then attach the bundle to multiple services.') }}</flux:subheading>
                    </div>

                    <x-action-message on="service-group-saved">{{ __('Service group saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveServiceGroup" class="mt-6 space-y-5">
                    <flux:input wire:model="groupName" :label="__('Group name')" type="text" required placeholder="Production" />

                    <div class="space-y-3">
                        <div>
                            <flux:heading>{{ __('Recipient groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Every recipient inside these groups becomes available to any service using this service group.') }}</flux:subheading>
                        </div>

                        @if ($this->recipientGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipient groups first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipientGroups as $recipientGroup)
                                    <label wire:key="service-group-recipient-group-option-{{ $recipientGroup->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $recipientGroup->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No recipients|{1} :count recipient|[2,*] :count recipients', $recipientGroup->recipients_count, ['count' => $recipientGroup->recipients_count]) }}</span>
                                        </div>

                                        <input
                                            wire:model="groupSelectedRecipientGroupIds"
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
                            <flux:subheading class="mt-1">{{ __('Attach one-off recipients that should always travel with this service group.') }}</flux:subheading>
                        </div>

                        @if ($this->recipients->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipients first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipients as $recipient)
                                    <label wire:key="service-group-recipient-option-{{ $recipient->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $recipient->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __($recipient->endpointTypeLabel()) }} · {{ $recipient->endpointTarget() }}</span>
                                        </div>

                                        <input
                                            wire:model="groupSelectedRecipientIds"
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
                        <flux:button variant="primary" type="submit">{{ $editingServiceGroupId ? __('Save service group') : __('Create service group') }}</flux:button>

                        @if ($editingServiceGroupId)
                            <flux:button type="button" variant="ghost" wire:click="cancelServiceGroupEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Service groups') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review the reusable bundles available to services and keep their routing ingredients up to date.') }}</flux:subheading>
                </div>

                <x-action-message on="service-group-deleted">{{ __('Service group removed.') }}</x-action-message>
            </div>

            @if ($this->serviceGroups->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No service groups have been created yet.') }}</p>
            @else
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach ($this->serviceGroups as $serviceGroup)
                        <div wire:key="service-group-row-{{ $serviceGroup->id }}" class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $serviceGroup->name }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span class="rounded-full bg-zinc-200 px-3 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ trans_choice('{1} :count service|[2,*] :count services', $serviceGroup->services_count, ['count' => $serviceGroup->services_count]) }}</span>
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ trans_choice('{1} :count direct recipient|[2,*] :count direct recipients', $serviceGroup->recipients_count, ['count' => $serviceGroup->recipients_count]) }}</span>
                                        <span class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ trans_choice('{1} :count recipient group|[2,*] :count recipient groups', $serviceGroup->recipient_groups_count, ['count' => $serviceGroup->recipient_groups_count]) }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <flux:button type="button" variant="ghost" wire:click="editServiceGroup({{ $serviceGroup->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button type="button" variant="danger" wire:click="confirmServiceGroupDeletion({{ $serviceGroup->id }})">{{ __('Delete') }}</flux:button>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div>
                                    <div class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Recipient groups') }}</div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($serviceGroup->recipientGroups->isEmpty())
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                        @else
                                            @foreach ($serviceGroup->recipientGroups as $recipientGroup)
                                                <span wire:key="service-group-recipient-group-chip-{{ $serviceGroup->id }}-{{ $recipientGroup->id }}" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $recipientGroup->name }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Direct recipients') }}</div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($serviceGroup->recipients->isEmpty())
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                        @else
                                            @foreach ($serviceGroup->recipients as $recipient)
                                                <span wire:key="service-group-recipient-chip-{{ $serviceGroup->id }}-{{ $recipient->id }}" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $recipient->name }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
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
                    @elseif ($deleteConfirmationType === 'service-group')
                        {{ __('This will permanently delete the service group ":name".', ['name' => $deleteConfirmationName]) }}
                    @endif
                </flux:subheading>
            </div>

            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Linked assignments will be removed with it, but recipients and recipient groups themselves will stay in place.') }}</p>

            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
