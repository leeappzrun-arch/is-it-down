<?php

use App\Concerns\ServiceValidation;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\ServiceTemplate;
use App\Support\Services\ServiceData;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Template management')] class extends Component {
    use ServiceValidation;

    public string $search = '';

    public ?int $editingServiceTemplateId = null;

    public string $templateName = '';

    public string $serviceName = '';

    public int $intervalSeconds = Service::INTERVAL_1_MINUTE;

    public string $expectType = Service::EXPECT_NONE;

    public string $expectValue = '';

    /** @var array<int, array{name: string, value: string}> */
    public array $additionalHeaders = [];

    public bool $sslExpiryNotificationsEnabled = false;

    /** @var array<int, string> */
    public array $selectedServiceGroupIds = [];

    /** @var array<int, string> */
    public array $selectedRecipientGroupIds = [];

    /** @var array<int, string> */
    public array $selectedRecipientIds = [];

    public bool $showDeleteConfirmationModal = false;

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
     * Get the saved service templates.
     */
    #[Computed]
    public function templates()
    {
        $templates = ServiceTemplate::query()
            ->orderBy('name')
            ->get();

        if ($this->searchTerm() === '') {
            return $templates;
        }

        return $templates
            ->filter(function (ServiceTemplate $template): bool {
                return $this->matchesSearch([
                    $template->name,
                    $template->serviceName(),
                    $template->intervalLabel(),
                    $template->expectSummary(),
                    $template->additionalHeadersSummary(),
                    $template->sslExpiryNotificationsEnabled() ? 'SSL expiry notifications enabled' : 'SSL expiry notifications disabled',
                    collect($template->configuredAdditionalHeaders())->pluck('name')->all(),
                    collect($template->configuredAdditionalHeaders())->pluck('value')->all(),
                    $this->selectedLabels($template->selectedServiceGroupIds(), $this->serviceGroups),
                    $this->selectedLabels($template->selectedRecipientGroupIds(), $this->recipientGroups),
                    $this->selectedLabels($template->selectedRecipientIds(), $this->recipients),
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
     * Create or update a service template.
     */
    public function saveTemplate(): void
    {
        $this->additionalHeaders = ServiceData::normalizeAdditionalHeaders($this->additionalHeaders);

        $validated = $this->validate($this->serviceTemplateRules());

        ServiceTemplate::query()->updateOrCreate(
            ['id' => $this->editingServiceTemplateId],
            ServiceTemplateData::payload($validated),
        );

        $this->resetServiceTemplateForm();
        $this->dispatch('service-template-saved');
    }

    /**
     * Populate the form for editing.
     */
    public function editTemplate(int $serviceTemplateId): void
    {
        $template = ServiceTemplate::query()->findOrFail($serviceTemplateId);
        $configuration = $template->serviceConfiguration();

        $this->editingServiceTemplateId = $template->id;
        $this->templateName = $template->name;
        $this->serviceName = $configuration['name'];
        $this->intervalSeconds = $configuration['interval_seconds'];
        $this->expectType = $configuration['expect_type'] ?? Service::EXPECT_NONE;
        $this->expectValue = $configuration['expect_value'] ?? '';
        $this->additionalHeaders = $configuration['additional_headers'];
        $this->sslExpiryNotificationsEnabled = $configuration['ssl_expiry_notifications_enabled'];
        $this->selectedServiceGroupIds = array_map(fn (int $id): string => (string) $id, $configuration['service_group_ids']);
        $this->selectedRecipientGroupIds = array_map(fn (int $id): string => (string) $id, $configuration['recipient_group_ids']);
        $this->selectedRecipientIds = array_map(fn (int $id): string => (string) $id, $configuration['recipient_ids']);

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'service-template');
    }

    /**
     * Prompt to delete a service template.
     */
    public function confirmTemplateDeletion(int $serviceTemplateId): void
    {
        $template = ServiceTemplate::query()->findOrFail($serviceTemplateId);

        $this->deleteConfirmationId = $template->id;
        $this->deleteConfirmationName = $template->name;
        $this->showDeleteConfirmationModal = true;
    }

    /**
     * Cancel template editing.
     */
    public function cancelTemplateEditing(): void
    {
        $this->resetServiceTemplateForm();
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
     * Add an additional request header row.
     */
    public function addAdditionalHeader(): void
    {
        $this->additionalHeaders[] = [
            'name' => '',
            'value' => '',
        ];
    }

    /**
     * Remove an additional request header row.
     */
    public function removeAdditionalHeader(int $index): void
    {
        unset($this->additionalHeaders[$index]);

        $this->additionalHeaders = array_values($this->additionalHeaders);
    }

    /**
     * Delete the record selected in the confirmation modal.
     */
    public function deleteConfirmedItem(): void
    {
        $this->deleteTemplate($this->deleteConfirmationId);
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
     * Get the validation rules for templates.
     *
     * @return array<string, array<int, mixed>>
     */
    private function serviceTemplateRules(): array
    {
        return $this->serviceTemplateValidationRules($this->expectType, $this->editingServiceTemplateId);
    }

    /**
     * Reset the service template form to its default state.
     */
    private function resetServiceTemplateForm(): void
    {
        $this->reset([
            'editingServiceTemplateId',
            'templateName',
            'serviceName',
            'expectValue',
            'additionalHeaders',
            'selectedServiceGroupIds',
            'selectedRecipientGroupIds',
            'selectedRecipientIds',
        ]);

        $this->intervalSeconds = Service::INTERVAL_1_MINUTE;
        $this->expectType = Service::EXPECT_NONE;
        $this->sslExpiryNotificationsEnabled = false;
        $this->resetValidation();
    }

    /**
     * Close and reset the confirmation modal state.
     */
    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
        $this->deleteConfirmationId = null;
        $this->deleteConfirmationName = '';
    }

    /**
     * Delete a service template record.
     */
    private function deleteTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId === null) {
            return;
        }

        ServiceTemplate::query()->findOrFail($serviceTemplateId)->delete();

        if ($this->editingServiceTemplateId === $serviceTemplateId) {
            $this->resetServiceTemplateForm();
        }

        $this->dispatch('service-template-deleted');
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

    /**
     * Resolve selected labels from an option collection.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function selectedLabels(array $ids, Collection $options): array
    {
        return $options
            ->whereIn('id', $ids)
            ->pluck('name')
            ->values()
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Templates') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create reusable service blueprints without a URL, save them from existing services, and use them later to start new services faster.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('services.index')" wire:navigate>
                {{ __('Manage services') }}
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
            :label="__('Search templates')"
            type="search"
            :placeholder="__('Search by template name, service defaults, or routing')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <div
                x-data="{ highlight: false, timeout: null, focusForm() { this.$el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' }); this.$nextTick(() => this.$el.querySelector('input, select, textarea, button')?.focus({ preventScroll: true })); this.highlight = true; if (this.timeout) { clearTimeout(this.timeout); } this.timeout = setTimeout(() => { this.highlight = false }, 2200); } }"
                x-on:focus-form.window="if ($event.detail.form === 'service-template') { focusForm() }"
                :class="{ 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white shadow-lg shadow-sky-500/10 animate-pulse dark:ring-sky-300/60 dark:ring-offset-zinc-900': highlight }"
                class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $editingServiceTemplateId ? __('Edit template') : __('Create template') }}</flux:heading>
                        <flux:subheading class="mt-2">{{ __('Save a reusable service name, expectation, interval, and routing setup, then apply it when you start a new service.') }}</flux:subheading>
                    </div>

                    <x-action-message on="service-template-saved">{{ __('Template saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveTemplate" class="mt-6 space-y-5">
                    <flux:input wire:model="templateName" :label="__('Template name')" type="text" required placeholder="Standard website template" />
                    <flux:input wire:model="serviceName" :label="__('Default service name')" type="text" required placeholder="Marketing site" />

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
                            <flux:subheading class="mt-1">{{ __('Store the same content checks you want new services to start with.') }}</flux:subheading>
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

                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading>{{ __('Request options') }}</flux:heading>
                                <flux:subheading class="mt-1">{{ __('Store reusable extra headers and whether new services should send SSL expiry warnings.') }}</flux:subheading>
                            </div>

                            <flux:button type="button" variant="subtle" size="sm" wire:click="addAdditionalHeader">
                                {{ __('Add header') }}
                            </flux:button>
                        </div>

                        @if ($additionalHeaders === [])
                            <p class="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('This template will not add extra request headers unless you store them here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($additionalHeaders as $index => $header)
                                    <div wire:key="template-additional-header-{{ $index }}" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] dark:border-zinc-700 dark:bg-zinc-950">
                                        <flux:input wire:model="additionalHeaders.{{ $index }}.name" :label="__('Header name')" type="text" placeholder="X-Environment" />
                                        <flux:input wire:model="additionalHeaders.{{ $index }}.value" :label="__('Header value')" type="text" placeholder="production" />

                                        <div class="flex items-end">
                                            <flux:button type="button" variant="ghost" wire:click="removeAdditionalHeader({{ $index }})">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('additionalHeaders.*.name')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @error('additionalHeaders.*.value')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <label class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-4 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <input
                                wire:model="sslExpiryNotificationsEnabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                            >

                            <span class="min-w-0">
                                <span class="block font-medium">{{ __('Enable SSL expiry notifications') }}</span>
                                <span class="mt-1 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('New services created from this template will warn recipients when the SSL certificate is within 10 days of expiry, with alerts limited to once every 24 hours per service.') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Service groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Store reusable service-group assignments on the template.') }}</flux:subheading>
                        </div>

                        @if ($this->serviceGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create a service group first and it will appear here for assignment.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->serviceGroups as $serviceGroup)
                                    <label wire:key="template-service-group-option-{{ $serviceGroup->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
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

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Recipient groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Store reusable recipient-group assignments on the template.') }}</flux:subheading>
                        </div>

                        @if ($this->recipientGroups->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipient groups first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipientGroups as $recipientGroup)
                                    <label wire:key="template-recipient-group-option-{{ $recipientGroup->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
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

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Direct recipients') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Store direct recipients that should be attached each time the template is used.') }}</flux:subheading>
                        </div>

                        @if ($this->recipients->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create recipients first and they will be available here.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->recipients as $recipient)
                                    <label wire:key="template-recipient-option-{{ $recipient->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
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
                        <flux:button variant="primary" type="submit">{{ $editingServiceTemplateId ? __('Save template') : __('Create template') }}</flux:button>

                        @if ($editingServiceTemplateId)
                            <flux:button type="button" variant="ghost" wire:click="cancelTemplateEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Saved templates') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review each template, edit its defaults, or jump straight into a new service with the saved settings prefilled.') }}</flux:subheading>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-action-message on="service-template-deleted">{{ __('Template removed.') }}</x-action-message>
                </div>
            </div>

            @if ($this->templates->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No templates match your search.') : __('No templates have been created yet.') }}
                </p>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($this->templates as $template)
                        @php($templateServiceGroups = $this->serviceGroups->whereIn('id', $template->selectedServiceGroupIds())->values())
                        @php($templateRecipientGroups = $this->recipientGroups->whereIn('id', $template->selectedRecipientGroupIds())->values())
                        @php($templateRecipients = $this->recipients->whereIn('id', $template->selectedRecipientIds())->values())

                        <details
                            wire:key="service-template-row-{{ $template->id }}"
                            x-data="{ expanded: false }"
                            x-bind:open="expanded"
                            x-on:toggle="expanded = $el.open"
                            class="group rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40"
                        >
                            <summary class="list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 space-y-2">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $template->name }}</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Default service: :name', ['name' => $template->serviceName()]) }}</div>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <span class="rounded-full bg-sky-100 px-3 py-1 font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ $template->intervalLabel() }}</span>
                                            <span class="rounded-full bg-zinc-200 px-3 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $template->expectSummary() }}</span>
                                            <span class="rounded-full bg-violet-100 px-3 py-1 font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $template->additionalHeadersSummary() }}</span>
                                            @if ($template->sslExpiryNotificationsEnabled())
                                                <span class="rounded-full bg-cyan-100 px-3 py-1 font-medium text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300">{{ __('SSL expiry alerts on') }}</span>
                                            @endif
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                {{ trans_choice('{0} No saved assignments|{1} :count saved assignment|[2,*] :count saved assignments', $templateServiceGroups->count() + $templateRecipientGroups->count() + $templateRecipients->count(), ['count' => $templateServiceGroups->count() + $templateRecipientGroups->count() + $templateRecipients->count()]) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                        <span class="font-medium">{{ __('Template details') }}</span>
                                        <span class="rounded-full border border-zinc-300 px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] dark:border-zinc-600">
                                            {{ __('Expand') }}
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expand to review the saved defaults, assignments, and the shortcut into a new service.') }}</p>
                            </summary>
                            <div class="mt-4 space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Template summary') }}</div>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Use this template to start a new service with the same non-URL defaults already filled in.') }}</p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button variant="primary" :href="route('services.index', ['template' => $template->id])" wire:navigate>{{ __('Create service') }}</flux:button>
                                        <flux:button type="button" variant="ghost" wire:click="editTemplate({{ $template->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button type="button" variant="danger" wire:click="confirmTemplateDeletion({{ $template->id }})">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-4">
                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Default service name') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $template->serviceName() }}</div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Interval') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $template->intervalLabel() }}</div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Expectation') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $template->expectSummary() }}</div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('URL') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Added when you create the service') }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Additional headers') }}</div>

                                        @if ($template->configuredAdditionalHeaders() === [])
                                            <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No additional headers saved') }}</div>
                                        @else
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach ($template->configuredAdditionalHeaders() as $header)
                                                    <span wire:key="template-header-chip-{{ $template->id }}-{{ md5($header['name'].'-'.$header['value']) }}" class="rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                                        {{ $header['name'] }}: {{ $header['value'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('SSL expiry alerts') }}</div>
                                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $template->sslExpiryNotificationsEnabled() ? __('Enabled by default for new services created from this template') : __('Disabled by default') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-3">
                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Service groups') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($templateServiceGroups->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($templateServiceGroups as $serviceGroup)
                                                    <span wire:key="template-service-group-chip-{{ $template->id }}-{{ $serviceGroup->id }}" class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $serviceGroup->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Recipient groups') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($templateRecipientGroups->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($templateRecipientGroups as $recipientGroup)
                                                    <span wire:key="template-recipient-group-chip-{{ $template->id }}-{{ $recipientGroup->id }}" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $recipientGroup->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Direct recipients') }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($templateRecipients->isEmpty())
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None assigned') }}</span>
                                            @else
                                                @foreach ($templateRecipients as $recipient)
                                                    <span wire:key="template-recipient-chip-{{ $template->id }}-{{ $recipient->id }}" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $recipient->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
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
                    {{ __('This will permanently delete the template ":name".', ['name' => $deleteConfirmationName]) }}
                </flux:subheading>
            </div>

            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __('Services already created from this template will stay in place, but this saved starting point will be removed.') }}</p>

            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
