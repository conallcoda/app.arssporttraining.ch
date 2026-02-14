<flux:main>
    <flux:heading size="xl" level="1" class="mb-8">Component Portal Demo</flux:heading>

    {{-- Section 1: Portal Modals --}}
    <div class="space-y-8">
        <section class="space-y-4">
            <flux:heading size="lg">Portal: Open components in modals</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                These buttons use Livewire.dispatch('portal:open') to open any registered component in a flyout modal.
            </flux:text>

            <div class="flex flex-wrap gap-3">
                <flux:button
                    variant="primary"
                    icon="list"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.exercise-list',
                        props: { prefixUrl: true },
                        title: 'Browse Exercises',
                        variant: 'flyout'
                    })"
                >
                    Exercise List (flyout)
                </flux:button>

                <flux:button
                    variant="primary"
                    icon="users"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.athlete-list',
                        props: { prefixUrl: true },
                        title: 'Browse Athletes',
                        variant: 'flyout'
                    })"
                >
                    Athlete List (flyout)
                </flux:button>

                <flux:button
                    variant="primary"
                    icon="folder-tree"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.category-tree',
                        props: { prefixUrl: true },
                        title: 'Category Tree',
                        variant: 'flyout'
                    })"
                >
                    Category Tree (flyout)
                </flux:button>

                <flux:button
                    variant="filled"
                    icon="dumbbell"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.equipment-list',
                        props: { prefixUrl: true },
                        title: 'Equipment',
                        variant: 'default'
                    })"
                >
                    Equipment (center modal)
                </flux:button>

                <flux:button
                    variant="filled"
                    icon="sliders-horizontal"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.modifiers-list',
                        props: { prefixUrl: true },
                        title: 'Modifiers',
                        variant: 'default'
                    })"
                >
                    Modifiers (center modal)
                </flux:button>
            </div>
        </section>

        <flux:separator />

        {{-- Section 2: Portal with Options --}}
        <section class="space-y-4">
            <flux:heading size="lg">Portal: Components with options</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                Pass options to customize component behavior. For example, hide the add button or enable compact mode.
            </flux:text>

            <div class="flex flex-wrap gap-3">
                <flux:button
                    variant="subtle"
                    icon="eye"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.exercise-list',
                        props: { prefixUrl: true, options: { showAddButton: false } },
                        title: 'Exercises (read-only)',
                        variant: 'flyout'
                    })"
                >
                    Exercise List (no add button)
                </flux:button>

                <flux:button
                    variant="subtle"
                    icon="minimize-2"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.athlete-list',
                        props: { prefixUrl: true, options: { showAddButton: false, compact: true } },
                        title: 'Athletes (compact, read-only)',
                        variant: 'flyout'
                    })"
                >
                    Athlete List (compact, no add)
                </flux:button>

                <flux:button
                    variant="subtle"
                    icon="folder-tree"
                    x-on:click="Livewire.dispatch('portal:open', {
                        component: 'database.category-tree',
                        props: { prefixUrl: true, options: { showAddButton: false } },
                        title: 'Categories (read-only)',
                        variant: 'flyout'
                    })"
                >
                    Category Tree (no add button)
                </flux:button>
            </div>
        </section>

        <flux:separator />

        {{-- Section 3: Inline Components with Options --}}
        <section class="space-y-4">
            <flux:heading size="lg">Inline: Embedded components with options</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                Components rendered inline using &lt;livewire:...&gt; with different option configurations.
            </flux:text>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-2">
                    <flux:heading size="base">Equipment List (default)</flux:heading>
                    <livewire:database.equipment-list :prefix-url="true" />
                </div>

                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-2">
                    <flux:heading size="base">Equipment List (no add, compact)</flux:heading>
                    <livewire:database.equipment-list :options="['showAddButton' => false, 'compact' => true]" :prefix-url="true" />
                </div>
            </div>
        </section>
    </div>
</flux:main>
