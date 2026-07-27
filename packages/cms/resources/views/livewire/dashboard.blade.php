<div class="p-8">
    <div class="max-w-2xl mx-auto text-center space-y-6">
        <flux:heading size="xl">
            {{ __('Welcome to') }} {{ config('cms.name') ?? config('app.name', 'CMS') }}
        </flux:heading>

        <flux:text class="text-lg">
            {{ __('Use the sidebar to navigate.') }}
        </flux:text>
    </div>
</div>
