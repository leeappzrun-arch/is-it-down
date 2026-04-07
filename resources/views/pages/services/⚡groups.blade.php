<?php

use App\Concerns\ServiceValidation;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service group management')] class extends Component {
    use ServiceValidation;

    public string $search = '';

    public ?int $editingServiceGroupId = null;

    public string $groupName = '';

    /** @var array<int, string> */
    public array $selectedServiceIds = [];

    /** @var array<int, string> */
    public array $groupSelectedRecipientGroupIds = [];

    /** @var array<int, string> */
    public array $groupSelectedRecipientIds = [];

    public bool $showDeleteConfirmationModal = false;

    public ?int $deleteConfirmationId = null;

    public string $deleteConfirmationName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function services()
    {
        return Service::query()
            ->orderBy('name')
            ->orderBy('url')
            ->get();
    }

    #[Computed]
    public function recipients()
    {
        return Recipient::query()
            ->orderBy('name')
            ->orderBy('endpoint')
            ->get();
    }

    #[Computed]
    public function recipientGroups()
    {
        return RecipientGroup::query()
            ->withCount('recipients')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function serviceGroups()
    {
        $groups = ServiceGroup::query()
            ->with([
                'services:id,name',
                'recipientGroups:id,name',
                'recipients:id,name,endpoint',
            ])
            ->withCount(['services', 'recipientGroups', 'recipients'])
            ->orderBy('name')
            ->get();

        if ($this->searchTerm() === '') {
            return $groups;
        }

        return $groups
            ->filter(fn (ServiceGroup $group): bool => $this->matchesSearch([
                $group->name,
                $group->services->pluck('name')->all(),
                $group->recipientGroups->pluck('name')->all(),
                $group->recipients->pluck('name')->all(),
            ]))
            ->values();
    }

    public function saveServiceGroup(): void
    {
        $validated = $this->validate($this->serviceGroupRules());

        $serviceGroup = ServiceGroup::query()->updateOrCreate(
            ['id' => $this->editingServiceGroupId],
            ['name' => trim($validated['groupName'])],
        );

        $serviceGroup->services()->sync($validated['selectedServiceIds'] ?? []);
        $serviceGroup->recipientGroups()->sync($validated['groupSelectedRecipientGroupIds'] ?? []);
        $serviceGroup->recipients()->sync($validated['groupSelectedRecipientIds'] ?? []);

        $this->resetServiceGroupForm();
        $this->dispatch('service-group-saved');
    }

    public function editServiceGroup(int $serviceGroupId): void
    {
        $serviceGroup = ServiceGroup::query()
            ->with(['services:id', 'recipientGroups:id', 'recipients:id'])
            ->findOrFail($serviceGroupId);

        $this->editingServiceGroupId = $serviceGroup->id;
        $this->groupName = $serviceGroup->name;
        $this->selectedServiceIds = $serviceGroup->services
            ->pluck('id')
            ->map(fn (int $serviceId): string => (string) $serviceId)
            ->all();
        $this->groupSelectedRecipientGroupIds = $serviceGroup->recipientGroups
            ->pluck('id')
            ->map(fn (int $groupId): string => (string) $groupId)
            ->all();
        $this->groupSelectedRecipientIds = $serviceGroup->recipients
            ->pluck('id')
            ->map(fn (int $recipientId): string => (string) $recipientId)
            ->all();

        $this->resetValidation();
        $this->dispatch('focus-form', form: 'service-group');
    }

    public function confirmServiceGroupDeletion(int $serviceGroupId): void
    {
        $serviceGroup = ServiceGroup::query()->findOrFail($serviceGroupId);

        $this->deleteConfirmationId = $serviceGroup->id;
        $this->deleteConfirmationName = $serviceGroup->name;
        $this->showDeleteConfirmationModal = true;
    }

    public function cancelServiceGroupEditing(): void
    {
        $this->resetServiceGroupForm();
    }

    public function deleteConfirmedItem(): void
    {
        if ($this->deleteConfirmationId !== null) {
            ServiceGroup::query()->findOrFail($this->deleteConfirmationId)->delete();

            if ($this->editingServiceGroupId === $this->deleteConfirmationId) {
                $this->resetServiceGroupForm();
            }

            $this->dispatch('service-group-deleted');
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
    private function serviceGroupRules(): array
    {
        return [
            ...$this->serviceGroupValidationRules($this->editingServiceGroupId),
            'selectedServiceIds' => ['array'],
            'selectedServiceIds.*' => ['integer', Rule::exists('services', 'id')],
        ];
    }

    private function resetServiceGroupForm(): void
    {
        $this->reset([
            'editingServiceGroupId',
            'groupName',
            'selectedServiceIds',
            'groupSelectedRecipientGroupIds',
            'groupSelectedRecipientIds',
        ]);

        $this->resetValidation();
    }

    private function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmationModal = false;
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
                <flux:heading size="xl" level="1">{{ __('Service groups') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create reusable service groups, and optionally attached Recipient Groups and Recipients to them.') }}</flux:subheading>
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
            :label="__('Search service groups')"
            type="search"
            :placeholder="__('Search by group, service, recipient group, or recipient')"
        />
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,28rem)_minmax(0,1fr)]">
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
                        <flux:subheading class="mt-2">{{ __('Attach services, direct recipients, and recipient groups without leaving the service group workflow.') }}</flux:subheading>
                    </div>

                    <x-action-message on="service-group-saved">{{ __('Service group saved.') }}</x-action-message>
                </div>

                <form wire:submit="saveServiceGroup" class="mt-6 space-y-5">
                    <flux:input wire:model="groupName" :label="__('Group name')" type="text" required placeholder="Production" />

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading>{{ __('Services') }}</flux:heading>
                                <flux:subheading class="mt-1">{{ __('Link services to this bundle from the group side.') }}</flux:subheading>
                            </div>

                            <flux:button variant="subtle" size="sm" :href="route('services.index')" wire:navigate>
                                {{ __('Open services') }}
                            </flux:button>
                        </div>

                        @if ($this->services->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Create services from the Services page and they will appear here for assignment.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($this->services as $service)
                                    <label wire:key="service-option-{{ $service->id }}" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="min-w-0">
                                            <span class="block font-medium">{{ $service->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $service->url }}</span>
                                        </div>

                                        <input
                                            wire:model="selectedServiceIds"
                                            type="checkbox"
                                            value="{{ $service->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                                        >
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedServiceIds.*')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div>
                            <flux:heading>{{ __('Recipient groups') }}</flux:heading>
                            <flux:subheading class="mt-1">{{ __('Every recipient inside these groups becomes available to any linked service.') }}</flux:subheading>
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

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-950/40">
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
                    <flux:heading size="lg">{{ __('Managed service groups') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Review the reusable bundles available to services and keep their linked services and recipients up to date.') }}</flux:subheading>
                </div>

                <x-action-message on="service-group-deleted">{{ __('Service group removed.') }}</x-action-message>
            </div>

            @if ($this->serviceGroups->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ trim($search) !== '' ? __('No service groups match your search.') : __('No service groups have been created yet.') }}
                </p>
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
                                    <div class="mb-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Services') }}</div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($serviceGroup->services->isEmpty())
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('None linked') }}</span>
                                        @else
                                            @foreach ($serviceGroup->services as $service)
                                                <span wire:key="service-group-service-chip-{{ $serviceGroup->id }}-{{ $service->id }}" class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $service->name }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>

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
                <flux:subheading class="mt-2">{{ __('This will permanently delete the service group ":name". Linked services and routing assignments will be removed, but the underlying services, recipients, and recipient groups will stay in place.', ['name' => $deleteConfirmationName]) }}</flux:subheading>
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="cancelDeleteConfirmation">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteConfirmedItem">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
