<div class="space-y-6">
    <x-section title="Athletes & Groups">
        @foreach ($fields as $field)
            <x-form-field :field="$field" />
        @endforeach
    </x-section>
</div>
