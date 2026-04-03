<?php

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
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

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('A quick view of the records currently configured across the application.') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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

    <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
        {{ auth()->user()?->isAdmin()
            ? __('Select a card to jump straight into the matching management page.')
            : __('These totals are visible to all authenticated users, while management pages remain admin only.') }}
    </div>
</section>
