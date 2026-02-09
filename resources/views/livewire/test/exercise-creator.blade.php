<flux:main>
    <div class="grid grid-cols-[35%_65%] gap-8">
        <div class="space-y-4">
            <flux:heading size="lg">Exercise Creator</flux:heading>
            <form class="space-y-4">
                @foreach ($this->fieldsets as $fieldset)
                    <x-cms.form.fieldset
                        :fieldset="$fieldset"
                        :prefix="$fieldset->prefix ?? 'data'"
                        :showLegend="true"
                    />
                @endforeach
            </form>
        </div>

        <div class="space-y-4">
            <flux:heading size="lg">Data</flux:heading>
            <pre class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
</flux:main>
