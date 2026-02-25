<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item separator="slash">Dashboard</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($this->selectedAthleteId)
        <div class="space-y-3">
            <flux:heading size="lg">Training Plans</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->trainingPlans as $plan)
                    <a wire:key="plan-{{ $plan->id }}" href="{{ route('athlete.training-plan', $plan->id) }}" wire:navigate>
                        <flux:card class="space-y-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors cursor-pointer">
                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>

                            <flux:text>
                                <span class="font-medium">Start:</span>
                                {{ $plan->startDate ? \Carbon\Carbon::parse($plan->startDate)->format('j M Y') : 'Not set' }}
                            </flux:text>

                            <div class="flex flex-wrap gap-1">
                                <flux:badge size="sm">{{ $plan->totalWeeks }} weeks</flux:badge>
                                @foreach ($plan->categories as $category)
                                    <flux:badge size="sm" :color="$category['color'] ?? 'zinc'">
                                        {{ $category['name'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </flux:card>
                    </a>
                @empty
                    <flux:text>No training plans found.</flux:text>
                @endforelse
            </div>
        </div>
    @endif
</div>
