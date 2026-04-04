<?php

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Get the monitored services shown in the dashboard overview.
     */
    #[Computed]
    public function monitoredServices()
    {
        return Service::query()
            ->orderByRaw("
                case current_status
                    when 'down' then 0
                    when 'up' then 1
                    else 2
                end
            ")
            ->orderBy('name')
            ->orderBy('url')
            ->get();
    }

    /**
     * Get the dashboard statistic cards.
     *
     * @return array<int, array{label: string, value: int, description: string, href: ?string}>
     */
    #[Computed]
    public function statCards(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return [
            [
                'label' => 'Recipients',
                'value' => Recipient::query()->count(),
                'description' => 'Configured delivery targets',
                'href' => $isAdmin ? route('recipients.index') : null,
            ],
            [
                'label' => 'Recipient groups',
                'value' => RecipientGroup::query()->count(),
                'description' => 'Reusable recipient collections',
                'href' => $isAdmin ? route('recipients.index') : null,
            ],
            [
                'label' => 'Services',
                'value' => Service::query()->count(),
                'description' => 'Monitored service endpoints',
                'href' => $isAdmin ? route('services.index') : null,
            ],
            [
                'label' => 'Service groups',
                'value' => ServiceGroup::query()->count(),
                'description' => 'Reusable service routing bundles',
                'href' => $isAdmin ? route('services.index') : null,
            ],
            [
                'label' => 'Users',
                'value' => User::query()->count(),
                'description' => 'Authenticated application accounts',
                'href' => $isAdmin ? route('users.index') : null,
            ],
            [
                'label' => 'API Keys',
                'value' => ApiKey::query()->count(),
                'description' => 'Provisioned access credentials',
                'href' => $isAdmin ? route('api-keys.index') : null,
            ],
        ];
    }
}; ?>

<section wire:poll.5s.visible class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('A quick view of the records currently configured across the application.') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Service status') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('Review the current monitoring state for every configured service.') }}</flux:subheading>
            </div>

            @if (auth()->user()?->isAdmin())
                <flux:button variant="ghost" :href="route('services.index')" wire:navigate>{{ __('Manage services') }}</flux:button>
            @endif
        </div>

        @if ($this->monitoredServices->isEmpty())
            <p class="mt-6 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No services have been created yet.') }}
            </p>
        @else
            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->monitoredServices as $service)
                    <div wire:key="dashboard-service-{{ $service->id }}" class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $service->name }}</div>
                                <div class="mt-1 break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $service->url }}</div>
                            </div>

                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $service->monitoringStatusClasses() }}">{{ __($service->monitoringStatusLabel()) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->statCards as $stat)
            @php($formattedValue = number_format($stat['value']))

            @if ($stat['href'])
                <a
                    wire:key="dashboard-stat-{{ $stat['label'] }}"
                    href="{{ $stat['href'] }}"
                    wire:navigate
                    class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __($stat['label']) }}</p>
                            <p class="text-4xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">{{ $formattedValue }}</p>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-3">
                            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __($stat['description']) }}</p>
                            <span class="text-sm font-medium text-zinc-900 transition group-hover:text-zinc-700 dark:text-zinc-100 dark:group-hover:text-zinc-200">
                                {{ __('Open') }}
                            </span>
                        </div>
                    </div>
                </a>
            @else
                <div
                    wire:key="dashboard-stat-{{ $stat['label'] }}"
                    class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __($stat['label']) }}</p>
                            <p class="text-4xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">{{ $formattedValue }}</p>
                        </div>

                        <div class="mt-auto space-y-2">
                            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __($stat['description']) }}</p>
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">{{ __('View only') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
